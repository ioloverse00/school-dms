const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function navigation() {
  let activeElement;
  const first = { offsetParent: {}, focus() { activeElement = this; } };
  const last = { offsetParent: {}, focus() { activeElement = this; } };
  const toggle = { offsetParent: {}, focus() { activeElement = this; } };
  const document = {
    get activeElement() { return activeElement; },
    querySelector() { return first; },
    querySelectorAll() { return [first, last]; },
    getElementById() { return toggle; },
  };
  const context = vm.createContext({
    window: { location: { search: '' }, localStorage: { getItem() { return null; }, setItem() {} }, matchMedia() { return { matches: false }; }, dispatchEvent() {} },
    URLSearchParams,
    Event: class { constructor(type) { this.type = type; } },
    document,
  });
  vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/init-alpine.js'), 'utf8'), context);
  const state = context.data();
  state.$nextTick = callback => callback();
  return { state, document, first, last, toggle };
}

test('opening mobile navigation focuses its close control; dismissal restores the trigger', () => {
  const { state, document, first, toggle } = navigation();
  state.toggleSideMenu();
  assert.equal(state.isSideMenuOpen, true);
  assert.equal(document.activeElement, first);
  state.closeSideMenu();
  assert.equal(state.isSideMenuOpen, false);
  assert.equal(document.activeElement, toggle);
});

test('desktop toggle collapses without opening a modal mobile drawer', () => {
  const {state} = navigation();
  state.desktopViewport = true;
  state.toggleNavigation();
  assert.equal(state.isDesktopSidebarCollapsed, true);
  assert.equal(state.isSideMenuOpen, false);
  state.toggleNavigation();
  assert.equal(state.isDesktopSidebarCollapsed, false);
});

test('Tab and Shift+Tab stay within the open navigation', () => {
  const { state, document, first, last } = navigation();
  state.toggleSideMenu();
  let prevented = 0;
  state.trapSideMenuFocus({ shiftKey: true, preventDefault() { prevented++; } });
  assert.equal(document.activeElement, last);
  state.trapSideMenuFocus({ shiftKey: false, preventDefault() { prevented++; } });
  assert.equal(document.activeElement, first);
  assert.equal(prevented, 2);
});

test('closing on desktop does not focus the hidden mobile trigger', () => {
  const { state, document, first, toggle } = navigation();
  state.toggleSideMenu();
  toggle.offsetParent = null;
  state.closeSideMenu();
  assert.equal(state.isSideMenuOpen, false);
  assert.equal(document.activeElement, first);
});

test('closed navigation leaves normal Tab behavior untouched', () => {
  const { state } = navigation();
  state.trapSideMenuFocus({ preventDefault() { assert.fail('Tab should not be trapped'); } });
});
