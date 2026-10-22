@extends('erp.layout')
@section('title','Settings')
@section('content')
<div class="fida-page-head"><div><h1>Settings</h1><p>Manage your organization preferences and system configuration.</p></div></div>
<div class="fida-settings">
<nav class="fida-settings-nav"><a href="#company">▣ Company Profile</a><a href="#branches">⌂ Branches</a><a href="#periods">▦ Fiscal Year</a><a href="#branding">◈ Branding & Appearance</a><a href="#security">♧ Backup & Security</a><a href="{{ route('erp.profile') }}">◉ User Preferences</a></nav>
<div class="fida-settings-main">
<section class="fida-panel" id="company"><div class="fida-panel-heading"><h2>Company Profile</h2><span class="fida-chart-note">Business information</span></div>
<div class="fida-panel-body">
<form method="POST" action="{{ route('erp.settings.company') }}">@csrf @method('PUT')
<div class="fida-settings-grid">
<div><label class="erp-label">Company Name *</label><input class="erp-field" name="name" value="{{ old('name',$company->name) }}" required></div>
<div><label class="erp-label">Base Currency</label><input class="erp-field" value="{{ $currency }}" readonly title="Requires audited conversion workflow to change after postings"></div>
<div><label class="erp-label">Default Language</label><select class="erp-field" name="locale"><option value="en" @selected($company->locale==='en')>English</option><option value="fa" @selected($company->locale==='fa')>Dari / فارسی</option><option value="ps" @selected($company->locale==='ps')>Pashto / پښتو</option></select></div>
<div><label class="erp-label">Timezone</label><select class="erp-field" name="timezone">@foreach(['Asia/Kabul','UTC','Asia/Dubai','Asia/Muscat'] as $tz)<option value="{{ $tz }}" @selected($company->timezone===$tz)>{{ $tz }}</option>@endforeach</select></div>
</div><p class="fida-chart-note">Company base currency cannot be changed after financial postings through this form.</p>
<div style="display:flex;justify-content:flex-end"><button class="erp-btn" type="submit">Save Changes</button></div></form>
</div></section>
<section class="fida-panel" id="branding"><div class="fida-panel-heading"><h2>Branding & Appearance</h2></div><div class="fida-panel-body">
<div class="fida-row" style="gap:14px"><img width="64" height="64" src="{{ asset('fida-erp/fidaerp-mark.svg') }}" alt="FidaERP logo"><div><strong>FidaERP Institutional Theme</strong><p class="fida-chart-note">Navy, blue and white reference design</p></div></div>
<div class="fida-insight-row">Primary Color <b style="color:#072f64">● #072F64</b></div><div class="fida-insight-row">Theme <b>Light</b></div>
<p class="fida-chart-note">Company-specific branding and dark mode require persistent settings and are not yet configurable.</p>
</div></section>
<section class="fida-panel" id="branches"><div class="fida-panel-heading"><h2>Branches</h2><button class="erp-btn" type="button" data-open-modal="fida-branch-modal">＋ Add Branch</button></div>
<div class="fida-table-wrap"><table class="fida-table"><thead><tr><th>Code</th><th>Branch</th><th>Status</th></tr></thead><tbody>
@foreach($branches as $branch)<tr><td>{{ $branch->code }}</td><td>{{ $branch->name }}</td><td><span class="fida-pill {{ $branch->active?'green':'red' }}">{{ $branch->active?'Active':'Inactive' }}</span></td></tr>@endforeach
</tbody></table></div></section>
<section class="fida-panel" id="periods"><div class="fida-panel-heading"><h2>Fiscal Year & Accounting</h2><a href="{{ route('erp.finance.index') }}">Finance →</a></div>
<div class="fida-panel-body"><div class="fida-insight-row">Base currency <b>{{ $currency }}</b></div><div class="fida-insight-row">User accounts <b>{{ $usersCount }}</b></div></div>
<div class="fida-table-wrap"><table class="fida-table"><thead><tr><th>Period</th><th>Start</th><th>End</th><th>Status</th></tr></thead><tbody>
@foreach($periods as $p)<tr><td>{{ $p->name }}</td><td>{{ $p->starts_on }}</td><td>{{ $p->ends_on }}</td><td><span class="fida-pill {{ $p->is_closed?'red':'green' }}">{{ $p->is_closed?'Closed':'Open' }}</span></td></tr>@endforeach
</tbody></table></div></section>
<section class="fida-panel" id="security" style="grid-column:1/-1"><div class="fida-panel-heading"><h2>Backup & Security</h2></div><div class="fida-panel-body"><p>Database-level backups and recovery procedures must be managed by the platform administrator. Tenant self-service backup, MFA and recovery testing are not yet available.</p></div></section>
</div>
</div>
<dialog class="fida-modal" id="fida-branch-modal">
<form method="POST" action="{{ route('erp.settings.branches.store') }}">@csrf
<div class="fida-modal-header"><h2>Add Branch</h2><button type="button" class="fida-icon-button" data-close-modal aria-label="Close">×</button></div>
<div class="fida-modal-body"><div class="fida-settings-grid"><div><label class="erp-label">Branch Code *</label><input class="erp-field" name="code" pattern="[A-Z0-9_-]+" placeholder="KBL-02" required maxlength="24"></div><div><label class="erp-label">Branch Name *</label><input class="erp-field" name="name" placeholder="Kabul Branch" required maxlength="190"></div></div></div>
<div class="fida-modal-footer"><button type="button" class="erp-btn secondary" data-close-modal>Cancel</button><button type="submit" class="erp-btn">Create Branch</button></div>
</form></dialog>
@endsection
