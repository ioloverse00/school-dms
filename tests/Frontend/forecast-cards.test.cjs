const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = {module:{exports:{}}, console};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../../public/assets/js/forecast-cards.js'), 'utf8'), context);
const {present} = context.module.exports;

test('cards use peak month workload rather than entire forecast horizon or unsupported staffing advice', () => {
 const cards = present({peak_request_periods:{months:[{month:'2027-06',requests:240}]}, expected_processing_workload:{avg_minutes_per_request:10,total_hours:294}, staffing_needs:{estimated_staff_needed_on_peak_day:1,monthly_surge_recommendations:[{message:'Add one staff'}]}});
 assert.equal(cards.peak.primary, 'June 2027');
 assert.equal(cards.workload.primary, '40 hours');
 assert.match(cards.workload.secondary, /June 2027 only/);
 assert.match(cards.workload.secondary, /10 minutes per request/);
 assert.equal(cards.staffing.primary, 'Capacity inputs needed');
 assert.doesNotMatch(JSON.stringify(cards), /Add one staff|294/);
});

test('missing and zero demand do not manufacture a peak or a workload estimate', () => {
 assert.equal(present({}).peak.primary, 'No predicted demand');
 const cards = present({peak_request_periods:{months:[{month:'2027-06',requests:0}]}});
 assert.equal(cards.peak.primary, 'No predicted demand');
 assert.equal(cards.workload.primary, 'Handling time needed');
});

test('top document belongs to peak month, not largest overall horizon total', () => {
 const cards = present({peak_request_periods:{months:[{month:'2027-06',requests:100}]}}, {
  months:['2027-05','2027-06'],per_document_type:[
   {name:'Transcript of Records',forecast:[1,70]},
   {name:'Enrollment certificate',forecast:[200,30]},
  ],
 });
 assert.match(cards.peak.documentDetail, /Transcript of Records \(70 expected requests\)/);
 assert.doesNotMatch(cards.peak.documentDetail, /Enrollment certificate/);
});

test('joint top documents are disclosed and missing month data is not invented', () => {
 const summary={peak_request_periods:{months:[{month:'2027-06',requests:20}]}};
 const cards=present(summary,{months:['2027-06'],per_document_type:[{name:'TOR',forecast:[10]},{name:'Grades',forecast:[10]}]});
 assert.match(cards.peak.documentDetail,/Joint top documents/);
 assert.match(cards.peak.documentDetail,/10 expected each/);
 assert.equal(present(summary,{}).peak.documentDetail,'');
});

test('staffing card uses capacity assessment outcomes', () => {
 for (const [assessment_status, expected] of [['sufficient','No additional staff needed'],['shared_support_needed','No additional staff needed'],['additional_staff_needed','2 additional staff needed'],['no_working_days','Assessment unavailable']]) {
  const cards=present({staffing_needs:{assessment_status,additional_staff:2,assigned_staff:18,support_staff:2,working_days:26,description:'Monthly planning estimate'}});
  assert.equal(cards.staffing.primary,expected);
  assert.match(cards.staffing.secondary,/Based on full attendance/);
 }
});
