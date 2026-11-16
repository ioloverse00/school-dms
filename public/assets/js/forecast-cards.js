(function (root, factory) {
 const api = factory();
 if (typeof module === 'object' && module.exports) module.exports = api;
 else root.PortalForecastCards = api;
})(typeof window === 'undefined' ? globalThis : window, function () {
 function monthLabel(month) {
  if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(month || '')) return 'Period unavailable';
  return new Date(month + '-01T00:00:00').toLocaleDateString('en-US', {month:'long', year:'numeric'});
 }
 function present(summary, forecast = {}) {
  const peak = summary?.peak_request_periods?.months?.[0];
  const minutes = summary?.expected_processing_workload?.avg_minutes_per_request;
  const hasPeak = peak && Number.isInteger(peak.requests) && peak.requests > 0;
  const validTime = Number.isFinite(minutes) && minutes > 0;
  const period = hasPeak ? monthLabel(peak.month) : null;
  const hours = hasPeak && validTime ? peak.requests * minutes / 60 : null;
  const monthIndex = Array.isArray(forecast.months) && hasPeak ? forecast.months.indexOf(peak.month) : -1;
  const ranked = monthIndex >= 0 && Array.isArray(forecast.per_document_type)
   ? forecast.per_document_type.map(row => ({name:row.name, count:row.forecast?.[monthIndex]}))
      .filter(row => typeof row.name === 'string' && Number.isInteger(row.count) && row.count > 0)
      .sort((a,b) => b.count - a.count || a.name.localeCompare(b.name)) : [];
  const leaders = ranked.length ? ranked.filter(row => row.count === ranked[0].count) : [];
  const documentDetail = leaders.length
   ? `${leaders.length > 1 ? 'Joint top documents' : 'Top document'}: ${leaders.map(row=>row.name).join(', ')} (${leaders[0].count.toLocaleString('en-US')} expected ${leaders.length > 1 ? 'each' : 'requests'}).`
   : '';
  const capacity = summary?.staffing_needs;
  const staffingLabels = {sufficient:'No additional staff needed',shared_support_needed:'No additional staff needed',additional_staff_needed:`${capacity?.additional_staff || 0} additional staff needed`,no_working_days:'Assessment unavailable'};
  const staffingReasons = {
   sufficient:'Estimated monthly workload fits the registered registrar capacity.',
   shared_support_needed:'The shared support pool can cover the estimated workload shortfall.',
   additional_staff_needed:'Estimated monthly workload exceeds the registered registrar capacity.',
   no_working_days:'No open working days are available in the forecast month.'
  };
  const staffing = capacity && staffingLabels[capacity.assessment_status]
   ? {primary:staffingLabels[capacity.assessment_status],secondary:`${staffingReasons[capacity.assessment_status]} ${Number.isInteger(capacity.registrar_count) ? `${capacity.registrar_count} registrars${Number.isFinite(capacity.productive_hours_per_staff_per_day) ? ` at ${capacity.productive_hours_per_staff_per_day} productive hours/day` : ''}; full attendance assumed.` : 'Based on full attendance.'}`}
   : {primary:'Capacity inputs needed', secondary:'Enter current staff count, productive hours and document handling times before assessing additional staff.'};
  return {
   peak: {primary: period || 'No predicted demand', secondary: hasPeak ? `${peak.requests.toLocaleString('en-US')} requests expected this month${leaders.length ? `, led by ${leaders.map(row=>row.name).join(' and ')} (${leaders[0].count.toLocaleString('en-US')}${leaders.length > 1 ? ' each' : ' requests'})` : ''}.` : 'No peak month can be identified from this forecast.', documentDetail},
   workload: {primary: hours === null ? 'Handling time needed' : `${hours.toLocaleString('en-US', {maximumFractionDigits:1})} hours`, secondary: hours === null ? 'Set a processing-time assumption to estimate workload.' : `${period} only. Assumes ${minutes} minutes per request; excludes other office duties.`},
   staffing,
  };
 }
 return {present, monthLabel};
});
