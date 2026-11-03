<#
    Hikvision bridge for the institute PC (Windows PowerShell 5.1, nothing to install).

    The app runs on the VPS and cannot reach the machine on the institute network. This script runs on an
    always-on PC in the institute: each run it asks the server which students/teachers must be added, updated
    or removed on the machine, applies them over ISAPI (digest login) and reports each result back.
    Faces and fingerprints are then enrolled on the machine against that Employee No.

    Files (same folder, e.g. C:\LGL):
      hikvision-bridge.ps1            this script
      hikvision-bridge-<id>.json      config downloaded from Admin > Attendance Machine; fill in the machine
                                      address, user and password. Keep it private.
      hikvision-bridge-<id>.log       written by this script

    Test once by hand:
      powershell -NoProfile -ExecutionPolicy Bypass -File C:\LGL\hikvision-bridge.ps1 -Config C:\LGL\hikvision-bridge-1.json
    Then run it every minute:
      schtasks /Create /SC MINUTE /MO 1 /TN "LGL Hikvision Bridge" /RU SYSTEM /TR "powershell -NoProfile -ExecutionPolicy Bypass -File C:\LGL\hikvision-bridge.ps1 -Config C:\LGL\hikvision-bridge-1.json"
#>
param([Parameter(Mandatory = $true)][string]$Config)

$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$cfg = Get-Content -Raw -Path $Config | ConvertFrom-Json
$logFile = [IO.Path]::ChangeExtension($Config, '.log')

function Write-Log([string]$Message) {
    Add-Content -Path $logFile -Value ('{0:yyyy-MM-dd HH:mm:ss} {1}' -f (Get-Date), $Message)
}

# Single run at a time (the scheduler may start a new run while a slow one is still working).
$mutex = New-Object System.Threading.Mutex($false, 'Global\LGLHikvisionBridge')
if (-not $mutex.WaitOne(0)) { exit 0 }

try {
    $server = ([string]$cfg.ServerUrl).TrimEnd('/')
    $bridge = "$server/biometric/bridge/$($cfg.BridgeToken)"
    $machine = ([string]$cfg.DeviceUrl).TrimEnd('/')
    if ($cfg.DevicePassword -eq 'CHANGE-ME' -or -not $cfg.DevicePassword) {
        Write-Log 'Set DeviceUrl, DeviceUser and DevicePassword in the config file first.'
        exit 1
    }
    $secure = ConvertTo-SecureString ([string]$cfg.DevicePassword) -AsPlainText -Force
    $credential = New-Object System.Management.Automation.PSCredential([string]$cfg.DeviceUser, $secure)

    function Invoke-Isapi([string]$Method, [string]$Path, $Body) {
        $json = ConvertTo-Json -InputObject $Body -Depth 6 -Compress
        try {
            $response = Invoke-WebRequest -UseBasicParsing -Method $Method -Uri "$machine$Path" -Credential $credential `
                -Body ([Text.Encoding]::UTF8.GetBytes($json)) -ContentType 'application/json; charset=utf-8' -TimeoutSec 20
            $text = [string]$response.Content
            # ISAPI JSON success is statusCode 1; some firmware answers 200 with an error body.
            try { $parsed = $text | ConvertFrom-Json } catch { $parsed = $null }
            if ($parsed -and $parsed.statusCode -and [int]$parsed.statusCode -ne 1) { return @{ ok = $false; body = $text } }
            return @{ ok = $true; body = $text }
        } catch {
            $text = $null
            if ($_.Exception.Response) {
                try { $text = (New-Object IO.StreamReader($_.Exception.Response.GetResponseStream())).ReadToEnd() } catch { }
            }
            if (-not $text) { $text = $_.Exception.Message }
            return @{ ok = $false; body = [string]$text }
        }
    }

    function Set-MachineUser($Row) {
        $user = @{
            employeeNo = [string]$Row.employee_no
            name = [string]$Row.name
            userType = 'normal'
            Valid = @{ enable = $true; beginTime = '2020-01-01T00:00:00'; endTime = '2037-12-31T23:59:59'; timeType = 'local' }
            doorRight = '1'
            RightPlan = @(@{ doorNo = 1; planTemplateNo = '1' })
        }
        $result = Invoke-Isapi 'PUT' '/ISAPI/AccessControl/UserInfo/SetUp?format=json' @{ UserInfo = $user }
        if (-not $result.ok -and $result.body -match 'notSupport|methodNotAllowed|Invalid Operation|404') {
            # Older firmware: add, or modify when the number already exists.
            $result = Invoke-Isapi 'POST' '/ISAPI/AccessControl/UserInfo/Record?format=json' @{ UserInfo = $user }
            if (-not $result.ok -and $result.body -match 'AlreadyExist') {
                $result = Invoke-Isapi 'PUT' '/ISAPI/AccessControl/UserInfo/Modify?format=json' @{ UserInfo = $user }
            }
        }
        return $result
    }

    function Remove-MachineUser($Row) {
        $list = @(@{ employeeNo = [string]$Row.employee_no })
        $result = Invoke-Isapi 'PUT' '/ISAPI/AccessControl/UserInfo/Delete?format=json' @{ UserInfoDelCond = @{ EmployeeNoList = $list } }
        if (-not $result.ok -and $result.body -match 'notSupport|methodNotAllowed|Invalid Operation|404') {
            $result = Invoke-Isapi 'PUT' '/ISAPI/AccessControl/UserInfoDetail/Delete?format=json' @{ UserInfoDetail = @{ mode = 'byEmployeeNo'; EmployeeNoList = $list } }
        }
        # Already gone from the machine counts as done.
        if (-not $result.ok -and $result.body -match 'notExist|NotExist|noUser|employeeNoNotExist') { $result = @{ ok = $true; body = $result.body } }
        return $result
    }

    try {
        $pending = Invoke-RestMethod -Method Get -Uri "$bridge/pending" -Headers @{ Accept = 'application/json' } -TimeoutSec 30
    } catch {
        Write-Log ('Server not reachable or bridge key wrong: ' + $_.Exception.Message)
        exit 1
    }

    $results = @()
    foreach ($row in @($pending.users)) {
        if ($null -eq $row) { continue }
        if ($row.action -eq 'delete') { $outcome = Remove-MachineUser $row } else { $outcome = Set-MachineUser $row }
        $failure = $null
        if (-not $outcome.ok) { $failure = ([string]$outcome.body).Substring(0, [Math]::Min(480, ([string]$outcome.body).Length)) }
        $results += @{ id = [int]$row.id; version = [int]$row.version; ok = [bool]$outcome.ok; error = $failure }
        Write-Log ("{0} {1} ({2}): {3}" -f $row.action, $row.employee_no, $row.name, $(if ($outcome.ok) { 'OK' } else { 'FAILED ' + $failure }))
    }

    if ($results.Count -gt 0) {
        $payload = ConvertTo-Json -InputObject @{ results = $results } -Depth 5 -Compress
        try {
            Invoke-RestMethod -Method Post -Uri "$bridge/results" -Headers @{ Accept = 'application/json' } `
                -Body ([Text.Encoding]::UTF8.GetBytes($payload)) -ContentType 'application/json; charset=utf-8' -TimeoutSec 30 | Out-Null
        } catch {
            # Rows stay pending on the server and are sent again next run.
            Write-Log ('Could not report results: ' + $_.Exception.Message)
            exit 1
        }
    }
} finally {
    $mutex.ReleaseMutex()
}
