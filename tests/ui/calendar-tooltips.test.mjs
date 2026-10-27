import test from 'node:test';
import assert from 'node:assert/strict';
import {calendarTooltipPosition} from '../../resources/js/calendar-tooltips.js';

test('a tooltip near the left edge stays inside the viewport', () => {
    assert.deepEqual(calendarTooltipPosition({left:0,width:100,top:100,bottom:200},{width:280,height:120},{width:1024,height:768}),{left:12,top:210});
});
test('a tooltip near the right edge does not overflow', () => {
    assert.deepEqual(calendarTooltipPosition({left:900,width:100,top:100,bottom:200},{width:280,height:120},{width:1024,height:768}),{left:732,top:210});
});
test('a tooltip near the bottom appears above the day, including narrow screens', () => {
    assert.deepEqual(calendarTooltipPosition({left:250,width:100,top:500,bottom:600},{width:280,height:120},{width:320,height:640}),{left:28,top:370});
});
