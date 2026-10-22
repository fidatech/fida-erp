@extends('erp.layout')
@section('title','My Profile')
@section('content')
@php($user=auth('erp_tenant')->user())
@php($roles=$user->roles()->pluck('name')->implode(', '))
<div class="fida-page-head"><div><h1>My Profile</h1><p>Manage your personal information, security settings and preferences.</p></div></div>
<section class="fida-panel">
<div class="fida-profile-hero">
<div class="fida-big-avatar">{{ mb_strtoupper(mb_substr($user->name,0,1)) }}</div>
<div><h2>{{ $user->name }} <span class="fida-pill {{ $user->active?'green':'red' }}">{{ $user->active?'Active':'Inactive' }}</span></h2><p style="font-size:15px;margin:2px 0">{{ $roles ?: 'Business user' }}</p><p class="fida-subtle">{{ app('erp.current_tenant')->business_name }}</p><div class="fida-row" style="flex-wrap:wrap"><span>✉ {{ $user->email }}</span><span>·</span><span>{{ $user->locale==='en'?'English':($user->locale==='fa'?'Dari':'Pashto') }}</span></div></div>
<div class="fida-profile-metadata"><div><span>Account ID</span><b>#{{ $user->id }}</b></div><div><span>Workspace</span><b>{{ app('erp.current_tenant')->slug }}</b></div><div><span>Joined</span><b>{{ $user->created_at?->format('d M Y') }}</b></div><div><span>Account Status</span><b class="fida-good">{{ $user->active?'Active':'Inactive' }}</b></div></div>
</div>
</section>
<div data-tabs-root>
<div class="fida-tabs" role="tablist">
<button class="active" type="button" data-tab="personal">Personal Information</button>
<button type="button" data-tab="security">Password Change</button>
<button type="button" data-tab="access">Security & Access</button>
</div>
<div class="fida-profile-layout" data-tab-panel="personal">
<section class="fida-panel"><div class="fida-panel-heading"><h2>Personal Information</h2><span class="fida-chart-note">Update your account details</span></div>
<div class="fida-panel-body">
<form method="POST" action="{{ route('erp.profile.save') }}">@csrf @method('PUT')
<div class="fida-form-grid">
<div><label class="erp-label">Full Name *</label><input class="erp-field" name="name" value="{{ old('name',$user->name) }}" required maxlength="190"></div>
<div><label class="erp-label">Email Address *</label><input class="erp-field" type="email" name="email" value="{{ old('email',$user->email) }}" required></div>
<div><label class="erp-label">Role</label><input class="erp-field" readonly value="{{ $roles ?: 'Unassigned' }}"></div>
<div><label class="erp-label">Preferred Language</label><input class="erp-field" readonly value="{{ strtoupper($user->locale) }}"></div>
<div><label class="erp-label">Business</label><input class="erp-field" readonly value="{{ app('erp.current_tenant')->business_name }}"></div>
<div><label class="erp-label">Account Status</label><input class="erp-field" readonly value="{{ $user->active?'Active':'Inactive' }}"></div>
</div>
<div style="display:flex;justify-content:flex-end;margin-top:20px"><button class="erp-btn" type="submit">Save Changes</button></div>
</form></div></section>
<section class="fida-panel"><div class="fida-panel-heading"><h2>Account Summary</h2></div><div class="fida-panel-body"><div class="fida-summary-tiles">
<div class="fida-summary-tile"><strong>{{ $user->roles()->count() }}</strong><span>Assigned roles</span></div>
<div class="fida-summary-tile"><strong>{{ \Illuminate\Support\Facades\DB::connection('erp_tenant')->table('branches')->count() }}</strong><span>Branches</span></div>
<div class="fida-summary-tile"><strong>{{ \Illuminate\Support\Facades\DB::connection('erp_tenant')->table('users')->count() }}</strong><span>Workspace users</span></div>
<div class="fida-summary-tile"><strong>{{ strtoupper($user->locale) }}</strong><span>Language</span></div>
</div><p class="fida-chart-note" style="margin-top:15px">Security options that are not configured are deliberately not shown as active.</p></div></section>
</div>
<section class="fida-panel" data-tab-panel="security" hidden><div class="fida-panel-heading"><h2>Change Password</h2></div><div class="fida-panel-body">
<form method="POST" action="{{ route('erp.password.change') }}">@csrf @method('PUT')
<div class="fida-form-grid">
<div><label class="erp-label">Current Password *</label><input class="erp-field" type="password" name="current_password" autocomplete="current-password" required></div>
<div><label class="erp-label">New Password *</label><input class="erp-field" type="password" name="password" minlength="12" autocomplete="new-password" required></div>
<div><label class="erp-label">Confirm Password *</label><input class="erp-field" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></div>
</div><p class="fida-chart-note">Use at least 12 characters. Existing sessions should be reviewed when credentials change.</p>
<button class="erp-btn" type="submit">Update Password</button></form></div></section>
<section class="fida-panel" data-tab-panel="access" hidden><div class="fida-panel-heading"><h2>Security & Access</h2></div><div class="fida-panel-body">
<p><strong>Current Roles:</strong> {{ $roles ?: 'None assigned' }}</p>
<p><strong>Access status:</strong> {{ $user->active?'Active':'Inactive' }}</p>
<p class="fida-chart-note">Multi-factor authentication and device activity tracking require additional configuration; they aren't enabled for this account.</p>
<a class="erp-btn secondary" href="{{ route('erp.settings.index') }}">Company Settings</a>
</div></section>
</div>
@endsection
