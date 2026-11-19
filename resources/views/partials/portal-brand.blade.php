<div class="portal-brand-expanded">
    @if($loginLogo = setting('institute_logo'))
        <img class="portal-brand-logo" src="{{ asset('storage/'.$loginLogo) }}" alt="{{ institute_name() }} logo">
    @else
        <span class="brand-mark"><i class="fas fa-graduation-cap" aria-hidden="true"></i></span>
        <span>{{ institute_name() }}</span>
    @endif
</div>
<div class="portal-brand-collapsed">
    @if($sidebarLogo = setting('sidebar_logo'))
        <img class="portal-brand-logo" src="{{ asset('storage/'.$sidebarLogo) }}" alt="{{ institute_name() }} logo">
    @else
        <span class="brand-mark"><i class="fas fa-graduation-cap" aria-hidden="true"></i></span>
    @endif
</div>
