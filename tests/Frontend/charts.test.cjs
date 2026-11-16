const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function api() {
    const sandbox = { module: { exports: {} } };
    vm.runInNewContext(fs.readFileSync('public/assets/js/portal-charts.js', 'utf8'), sandbox);
    return sandbox.module.exports;
}

test('forecast alignment preserves actual counts and bridges only the last actual month', () => {
    const result = JSON.parse(JSON.stringify(api().trend(['Jan', 'Feb'], [3, 6], ['Mar', 'Apr'], [8, 9])));
    assert.deepEqual(result.categories, ['Jan', 'Feb', 'Mar', 'Apr']);
    assert.deepEqual(result.series[0].data, [3, 6, null, null]);
    assert.deepEqual(result.series[1].data, [null, 6, 8, 9]);
});

test('AI horizon replacement supports twelve months without altering zero historical counts', () => {
    const labels = Array.from({length: 12}, (_, i) => `Future ${i}`);
    const result = JSON.parse(JSON.stringify(api().trend(['Past'], [0], labels, labels.map(() => 2))));
    assert.equal(result.categories.length, 13);
    assert.deepEqual(result.series[0].data, [0, ...Array(12).fill(null)]);
    assert.deepEqual(result.series[1].data, [0, ...Array(12).fill(2)]);
});

test('an empty history does not manufacture an actual data point', () => {
    const result = JSON.parse(JSON.stringify(api().trend([], [], ['Next'], [4])));
    assert.deepEqual(result.series[0].data, [null]);
    assert.deepEqual(result.series[1].data, [4]);
});
