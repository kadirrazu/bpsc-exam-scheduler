import test from 'node:test';
import assert from 'node:assert/strict';
import {structureTotals} from '../../resources/js/board-structure.js';

test('English and Bengali rows produce the requested 7 boards and 93 candidates', () => {
    for (const rows of [
        [{candidates_per_board:15, boards:3}, {candidates_per_board:12, boards:4}],
        [{candidates_per_board:'১৫', boards:'৩'}, {candidates_per_board:'১২', boards:'৪'}],
    ]) assert.deepEqual(structureTotals(rows), {boards:7, candidates:93});
});

test('Incomplete or invalid structures never replace manual totals', () => {
    for (const rows of [[], [{candidates_per_board:'',boards:''}], [{candidates_per_board:15,boards:''}],
        [{candidates_per_board:15,boards:0}], [{candidates_per_board:1.5,boards:2}],
        [{candidates_per_board:10000000,boards:2}], [{candidates_per_board:1,boards:100001}]]) {
        assert.equal(structureTotals(rows), null);
    }
});

test('Adding/removing a complete row updates totals while blank rows are ignored', () => {
    const rows=[{candidates_per_board:15,boards:3},{candidates_per_board:12,boards:4}];
    assert.deepEqual(structureTotals([...rows,{candidates_per_board:'',boards:''}]),{boards:7,candidates:93});
    assert.deepEqual(structureTotals([...rows,{candidates_per_board:5,boards:2}]),{boards:9,candidates:103});
    assert.deepEqual(structureTotals(rows.slice(1)),{boards:4,candidates:48});
});
