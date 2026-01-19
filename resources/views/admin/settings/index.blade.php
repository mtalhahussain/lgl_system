@extends('layouts.admin')

@section('title', 'System Settings')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-cog me-2"></i>System Settings</h2>
            <p class="text-muted mb-0">Configure your institute settings and preferences</p>
        </div>
    </div>

    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Institute Settings -->
                @if($settingGroups->has('institute'))
                <div class="card dashboard-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-building me-2"></i>Institute Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($settingGroups['institute'] as $setting)
                                <div class="col-md-6 mb-3">
                                    <label for="{{ $setting->key }}" class="form-label">{{ $setting->label }}</label>
                                    @if($setting->description)
                                        <small class="text-muted d-block">{{ $setting->description }}</small>
                                    @endif
                                    
                                    @switch($setting->type)
                                        @case('file')
                                            <input type="file" class="form-control" id="{{ $setting->key }}" name="{{ $setting->key }}" accept="image/*">
                                            @if($setting->value)
                                                <div class="mt-2">
                                                    <img src="{{ asset('storage/' . $setting->value) }}" alt="Current Logo" style="max-height: 60px;">
                                                </div>
                                            @endif
                                            @break
                                            
                                        @case('textarea')
                                            <textarea class="form-control" id="{{ $setting->key }}" name="{{ $setting->key }}" rows="3">{{ $setting->value }}</textarea>
                                            @break
                                            
                                        @case('email')
                                            <input type="email" class="form-control" id="{{ $setting->key }}" name="{{ $setting->key }}" value="{{ $setting->value }}">
                                            @break
                                            
                                        @case('url')
                                            <input type="url" class="form-control" id="{{ $setting->key }}" name="{{ $setting->key }}" value="{{ $setting->value }}">
                                            @break
                                            
                                        @default
                                            <input type="text" class="form-control" id="{{ $setting->key }}" name="{{ $setting->key }}" value="{{ $setting->value }}">
                                    @endswitch
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                <!-- Currency Settings -->
                @if($settingGroups->has('currency'))
                <div class="card dashboard-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-money-bill-wave me-2"></i>Currency Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($settingGroups['currency'] as $setting)
                                <div class="col-md-6 mb-3">
                                    <label for="{{ $setting->key }}" class="form-label">{{ $setting->label }}</label>
                                    @if($setting->description)
                                        <small class="text-muted d-block">{{ $setting->description }}</small>
                                    @endif
                                    
                                    @if($setting->type === 'select' && $setting->options)
                                        <select class="form-select" id="{{ $setting->key }}" name="{{ $setting->key }}">
                                            @foreach($setting->options as $value => $label)
                                                <option value="{{ $value }}" {{ $setting->value === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($setting->type === 'number')
                                        <input type="number" class="form-control" id="{{ $setting->key }}" name="{{ $setting->key }}" value="{{ $setting->value }}" min="0" step="0.01">
                                    @else
                                        <input type="text" class="form-control" id="{{ $setting->key }}" name="{{ $setting->key }}" value="{{ $setting->value }}">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Currency Preview -->
                        <div class="mt-3 p-3 bg-light rounded">
                            <h6>Currency Preview:</h6>
                            <span id="currency-preview" class="h5 text-primary">Rs. 15,000.00</span>
                        </div>
                    </div>
                </div>
                @endif

            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Save Actions -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Settings
                            </button>
                        </div>
                        <hr>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Settings will be applied immediately after saving.
                        </small>
                    </div>
                </div>

                <!-- Current Settings Preview -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-eye me-2"></i>Current Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Institute:</strong><br>
                            <span class="text-muted">{{ $settingGroups['institute']->where('key', 'institute_name')->first()->value ?? 'N/A' }}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Currency:</strong><br>
                            <span class="text-muted">{{ $settingGroups['currency']->where('key', 'currency_code')->first()->value ?? 'USD' }}</span>
                            ({{ $settingGroups['currency']->where('key', 'currency_symbol')->first()->value ?? '$' }})
                        </div>
                        <div class="mb-3">
                            <strong>Timezone:</strong><br>
                            <span class="text-muted">{{ $settingGroups['system']->where('key', 'timezone')->first()->value ?? 'UTC' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Currency mapping
    const currencySymbols = {
        'PKR': 'Rs.',
        'USD': '$',
        'EUR': '€',
        'GBP': '£'
    };
    
    // Auto-update currency symbol based on currency code selection
    const currencyCodeSelect = document.getElementById('currency_code');
    const currencySymbolInput = document.getElementById('currency_symbol');
    
    if (currencyCodeSelect && currencySymbolInput) {
        currencyCodeSelect.addEventListener('change', function() {
            const selectedCode = this.value;
            if (currencySymbols[selectedCode]) {
                currencySymbolInput.value = currencySymbols[selectedCode];
                updateCurrencyPreview();
            }
        });
    }
    
    // Currency preview update
    function updateCurrencyPreview() {
        const symbol = document.getElementById('currency_symbol')?.value || '$';
        const position = document.getElementById('currency_position')?.value || 'before';
        const decimals = document.getElementById('currency_decimals')?.value || 2;
        
        const amount = (15000).toFixed(decimals);
        const formatted = position === 'before' ? `${symbol} ${amount}` : `${amount} ${symbol}`;
        
        document.getElementById('currency-preview').textContent = formatted;
    }
    
    // Update preview on change
    ['currency_symbol', 'currency_position', 'currency_decimals'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', updateCurrencyPreview);
            element.addEventListener('input', updateCurrencyPreview);
        }
    });
    
    // Initial preview update
    updateCurrencyPreview();
});
</script>
@endsection