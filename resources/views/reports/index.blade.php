@extends('layouts.dashboard')
@section('title', 'Reports & analytics')
@section('content')
<div class="portal-page-header"><div><h1 class="portal-heading">Reports &amp; analytics</h1><p class="portal-subtitle">Request demand and recorded outcomes across academic periods.</p></div></div>
@if($errors->any())<div class="alert alert-danger" role="alert" x-data="{ errorVisible: true }" x-show="errorVisible" style="position:relative;padding-right:48px"><button type="button" aria-label="Dismiss error message" @click="errorVisible = false" style="position:absolute;top:8px;right:8px;width:32px;height:32px;border:0;background:transparent;color:inherit;font-size:24px;line-height:1;cursor:pointer">&times;</button><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="card portal-card mb-4">
<form method="GET" action="{{ url()->current() }}" class="row g-3">
<div class="col-md-6 col-xl-3"><label for="report-year">Academic year</label><select id="report-year" name="year" class="spark-field form-select-custom w-full"><option value="">All years</option>@foreach(collect($saved['years'])->sortByDesc('starts_on') as $id => $item)<option value="{{ $id }}" @selected(request('year') === $id)>{{ $item['name'] }}</option>@endforeach</select></div>
<div class="col-md-6 col-xl-3"><label for="report-term">Semester</label><select id="report-term" name="term" class="spark-field form-select-custom w-full"><option value="">All semesters</option>@foreach($saved['terms'] as $id => $item)<option value="{{ $id }}" @selected(request('term') === $id)>{{ $item['academic_year'] }} / {{ $item['name'] }}</option>@endforeach</select></div>
<div class="col-md-6 col-xl-3"><label for="report-from">From (optional override)</label><input type="date" id="report-from" name="from" class="spark-field form-control-custom w-full" value="{{ request('from') }}"></div>
<div class="col-md-6 col-xl-3"><label for="report-to">To (optional override)</label><input type="date" id="report-to" name="to" class="spark-field form-control-custom w-full" value="{{ request('to') }}"></div>
<div class="col-md-6 col-xl-3"><label for="report-status">Current status</label><select id="report-status" name="status" class="spark-field form-select-custom w-full"><option value="">All statuses</option>@foreach(\App\Support\RequestStatus::LABELS as $id => $label)<option value="{{ $id }}" @selected(request('status') === $id)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-6 col-xl-3"><label for="report-category">Student category</label><select id="report-category" name="category" class="spark-field form-select-custom w-full"><option value="">All categories</option>@foreach($categoryLabels as $id => $label)<option value="{{ $id }}" @selected(request('category') === $id)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-6 col-xl-3"><label for="report-document">Document</label><select id="report-document" name="document" class="spark-field form-select-custom w-full"><option value="">All documents</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected((string)request('document') === (string)$type->id)>{{ $type->name }}</option>@endforeach</select></div>
<div class="col-md-6 col-xl-3"><label for="report-scope">Request scope</label><select id="report-scope" name="scope" class="spark-field form-select-custom w-full">@foreach(['all'=>'Active and archived','active'=>'Active only','archived'=>'Archived only'] as $id=>$label)<option value="{{ $id }}" @selected($scope === $id)>{{ $label }}</option>@endforeach</select></div>
<div class="col-12 d-flex gap-2 flex-wrap"><button class="btn btn-primary" type="submit">Apply filters</button><a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reset</a></div>
</form>
<p class="portal-note mt-3 mb-0">Dates filter when requests were submitted. Date overrides take precedence over academic dates; otherwise the selected semester/year is used. Default: year to date.</p>
</section>
<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4"><div><strong>{{ \Carbon\Carbon::parse($from)->format('F j, Y') }} to {{ \Carbon\Carbon::parse($to)->format('F j, Y') }}</strong><p class="portal-note mb-0">Statuses reflect current outcomes, not a historical snapshot.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['format'=>'csv']) }}">Export CSV</a><a class="btn btn-primary" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}">Download PDF</a></div></div>
<div class="row g-4 mb-4">@foreach(['Total requests'=>$total,'Copies requested'=>$copies,'Completed'=>$statuses['completed'],'Archived requests'=>$archived] as $label=>$value)<div class="col-6 col-xl-3"><section class="card portal-card h-100"><div class="portal-note">{{ $label }}</div><strong class="h2">{{ number_format($value) }}</strong></section></div>@endforeach</div>
@if(!$total)<div class="alert alert-info">No requests match these filters. Try another date range or request scope.</div>@endif
<div class="row g-4">
<div class="col-lg-8"><section class="card portal-card h-100"><h2 class="h5">Monthly request trend</h2><div id="report-monthly-chart" role="img" aria-label="Monthly submitted request counts"></div></section></div>
<div class="col-lg-4"><section class="card portal-card h-100"><h2 class="h5">Requests by status</h2><div id="report-status-chart" role="img" aria-label="Request counts by current status"></div><p class="portal-note">Completed requests are counted by submission date. These totals do not measure staff productivity or processing time.</p></section></div>
<div class="col-lg-8"><section class="card portal-card h-100"><h2 class="h5">Document demand</h2><div id="report-document-chart" role="img" aria-label="Request counts by document type"></div></section></div>
<div class="col-lg-4"><section class="card portal-card h-100"><h2 class="h5">Student categories</h2><div id="report-category-chart" role="img" aria-label="Request counts by student category"></div>
@php
    $rankedCategories = collect($categories)->sortDesc();
    $largestCategoryCount = $rankedCategories->first() ?? 0;
    $leadingCategoryNames = $rankedCategories->filter(fn ($count) => $count === $largestCategoryCount)->keys()->map(fn ($key) => $categoryLabels[$key] ?? $key);
@endphp
<div class="border-top pt-3 mt-3">
    <h3 class="h6">Category breakdown</h3>
    <dl class="mb-0">
        @forelse($rankedCategories as $category => $count)
            <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom">
                <dt class="fw-normal">{{ $categoryLabels[$category] ?? $category }}</dt>
                <dd class="mb-0 text-end text-nowrap"><strong>{{ number_format($count) }}</strong> <span class="portal-note">({{ number_format($total > 0 ? $count / $total * 100 : 0, 1) }}%)</span></dd>
            </div>
        @empty
            <p class="portal-note">No category data for these filters.</p>
        @endforelse
    </dl>
    @if($total > 0 && $largestCategoryCount > 0)
        <p class="portal-note mt-3 mb-0"><strong>{{ $leadingCategoryNames->count() > 1 ? 'Joint most requests' : 'Most requests' }}:</strong> {{ $leadingCategoryNames->join(', ') }} &mdash; {{ number_format($largestCategoryCount / $total * 100, 1) }}%{{ $leadingCategoryNames->count() > 1 ? ' each' : '' }}.</p>
    @endif
    <p class="portal-note mt-2 mb-0">Share of {{ number_format($total) }} requests matching the selected filters.</p>
</div>
</section></div>
</div>
<p class="portal-note mt-3">Hover over chart points for exact counts. CSV and PDF exports include the filtered figures.</p>
@endsection
@push('scripts')
<script src="{{ asset('assets/vendor/apexcharts/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
 const series = {
  months: @json($months), statuses: @json($statuses), documents: @json($documents), categories: @json($categories)
 };
 const statusLabels = @json(\App\Support\RequestStatus::LABELS);
 const documentLabels = @json($labels);
 const categoryLabels = @json($categoryLabels);
 const charts = [];
 const mode = () => document.documentElement.dataset.bsTheme === 'dark' ? 'dark' : 'light';
 const colors = ['#293681','#4274D9','#95CCDD','#D0E7E6','#6786B1','#A8B5D9'];
 const number = value => Number(value).toLocaleString();
 const mount = (id, options, summary) => {
  const host = document.getElementById(id);
  host.setAttribute('aria-label', summary);
  if (!window.ApexCharts) { host.textContent = 'Chart unavailable. Download CSV or PDF to view the filtered figures.'; return; }
  const chart = new ApexCharts(host, {
   chart:{height:320,fontFamily:'inherit',toolbar:{show:false},background:'transparent',animations:{enabled:!matchMedia('(prefers-reduced-motion: reduce)').matches}},
   colors, theme:{mode:mode()}, noData:{text:'No requests match these filters'},
   dataLabels:{enabled:false}, tooltip:{y:{formatter:number}}, ...options
  });
  charts.push(chart); chart.render();
 };
 const describe = (data, labels = {}) => Object.entries(data).map(([key,value]) => `${labels[key] || key}: ${number(value)}`).join('; ') || 'No matching requests';
 mount('report-monthly-chart', {
  chart:{type:'area',height:320,fontFamily:'inherit',toolbar:{show:false},background:'transparent',animations:{enabled:!matchMedia('(prefers-reduced-motion: reduce)').matches}},
  series:[{name:'Requests',data:Object.values(series.months)}],xaxis:{categories:Object.keys(series.months),tickAmount:8},
  stroke:{width:2,curve:'straight'},fill:{type:'gradient',gradient:{opacityFrom:.3,opacityTo:.03}},
  yaxis:{min:0,forceNiceScale:true,decimalsInFloat:0}
 }, 'Monthly requests. '+describe(series.months));
 const statusEntries = Object.entries(series.statuses).filter(([,value])=>value>0);
 mount('report-status-chart', {
  chart:{type:'donut',height:320,fontFamily:'inherit',toolbar:{show:false},background:'transparent',animations:{enabled:!matchMedia('(prefers-reduced-motion: reduce)').matches}},
  series:statusEntries.map(([,value])=>value),labels:statusEntries.map(([key])=>statusLabels[key] || key),
  legend:{position:'bottom'},plotOptions:{pie:{donut:{size:'68%',labels:{show:true,total:{show:true,label:'Requests',formatter:ctx=>number(ctx.globals.seriesTotals.reduce((a,b)=>a+b,0))}}}}}
 }, 'Requests by status. '+describe(series.statuses,statusLabels));
 const bars = (id, data, labels, heading) => {
  const entries = Object.entries(data).sort((a,b)=>b[1]-a[1]);
  mount(id, {
   chart:{type:'bar',height:Math.max(300,entries.length*45+70),fontFamily:'inherit',toolbar:{show:false},background:'transparent',animations:{enabled:!matchMedia('(prefers-reduced-motion: reduce)').matches}},
   series:entries.length ? [{name:'Requests',data:entries.map(([,value])=>value)}] : [],
   xaxis:{categories:entries.map(([key])=>labels[key] || key),min:0,decimalsInFloat:0},
   yaxis:{labels:{maxWidth:250}},plotOptions:{bar:{horizontal:true,borderRadius:4,barHeight:'55%'}},
   dataLabels:{enabled:true,formatter:number}
  }, heading+'. '+describe(data,labels));
 };
 bars('report-document-chart',series.documents,documentLabels,'Document demand');
 const categoryEntries = Object.entries(series.categories).sort((a,b)=>b[1]-a[1]);
 mount('report-category-chart', {
  chart:{type:'bar',height:340,fontFamily:'inherit',toolbar:{show:false},background:'transparent',animations:{enabled:!matchMedia('(prefers-reduced-motion: reduce)').matches}},
  series:categoryEntries.length ? [{name:'Requests',data:categoryEntries.map(([,value])=>value)}] : [],
  xaxis:{categories:categoryEntries.map(([key])=>categoryLabels[key] || key),labels:{rotate:-35,trim:false,hideOverlappingLabels:false}},
  yaxis:{min:0,decimalsInFloat:0},
  plotOptions:{bar:{horizontal:false,columnWidth:'50%',borderRadius:4,distributed:true}},
  legend:{show:false},dataLabels:{enabled:true,formatter:number}
 }, 'Requests by student category. '+describe(series.categories,categoryLabels));
 new MutationObserver(()=>charts.forEach(chart=>chart.updateOptions({theme:{mode:mode()}}))).observe(document.documentElement,{attributes:true,attributeFilter:['data-bs-theme']});
});
</script>
@endpush
