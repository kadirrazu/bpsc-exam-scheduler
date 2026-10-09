export function structureTotals(rows) {
    const ascii = value => String(value ?? '').trim().replace(/[০-৯]/g, digit => String('০১২৩৪৫৬৭৮৯'.indexOf(digit)));
    let boards = 0, candidates = 0, entered = false;
    for (const row of rows) {
        const a = ascii(row.candidates_per_board), b = ascii(row.boards);
        if (!a && !b) continue;
        if (!/^\d+$/.test(a) || !/^\d+$/.test(b) || +a < 1 || +b < 1 || +a > 10000000 || +b > 100000) return null;
        entered = true;
        boards += +b;
        candidates += +a * +b;
    }
    return entered && boards <= 100000 && candidates <= 10000000 ? {boards, candidates} : null;
}
