import '@tabler/core/dist/js/tabler.min.js';
import {structureTotals} from './board-structure.js';
const type = document.querySelector('[data-exam-type]');
if (type) {
    const structure = document.querySelector('[data-board-structure]');
    const rows = structure?.querySelector('[data-structure-rows]');
    const reindex = () => rows?.querySelectorAll('[data-structure-row]').forEach((row, index) => {
        row.querySelector('[data-structure-candidates]').name = `board_structure[${index}][candidates_per_board]`;
        row.querySelector('[data-structure-boards]').name = `board_structure[${index}][boards]`;
        row.querySelector('[data-remove-structure]').hidden = false;
    });
    const calculate = () => {
        if (type.value !== 'viva' || !rows) return;
        const totals = structureTotals([...rows.querySelectorAll('[data-structure-row]')].map(row => ({
            candidates_per_board: row.querySelector('[data-structure-candidates]').value,
            boards: row.querySelector('[data-structure-boards]').value,
        })));
        if (!totals) return;
        document.querySelector('#board_count').value = String(totals.boards);
        document.querySelector('#candidate_count').value = String(totals.candidates);
    };
    const toggle = () => {
        const viva = type.value === 'viva';
        for (const kind of ['center','board']) {
            const field = document.querySelector(`[data-count="${kind}"]`);
            if (!field) continue;
            const show = Boolean(type.value) && (kind === 'board' ? viva : !viva);
            field.hidden = !show;
            field.querySelectorAll('input').forEach(input => {input.disabled = !show; input.required = show && kind === 'board';});
        }
        const end = document.querySelector('[data-end-time]');
        if (end) {end.hidden = viva; end.querySelector('input').disabled = viva;}
        if (structure) {structure.hidden = !viva; structure.querySelectorAll('input').forEach(input => input.disabled = !viva);}
    };
    structure?.querySelector('[data-add-structure]')?.addEventListener('click', () => {
        const row = rows.firstElementChild.cloneNode(true);
        row.querySelectorAll('input').forEach(input => {input.value = '';input.disabled = false;});
        rows.append(row); reindex(); row.querySelector('input').focus();
    });
    if (structure) {
        structure.querySelector('[data-add-structure]').hidden = false;
        structure.addEventListener('input', calculate);
        structure.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-structure]');
            if (!button) return;
            if (rows.children.length > 1) button.closest('[data-structure-row]').remove();
            else rows.querySelectorAll('input').forEach(input => input.value = '');
            reindex(); calculate();
        });
    }
    type.addEventListener('change', () => {toggle(); calculate();});
    reindex(); toggle(); // Never overwrite a saved manual total on initial page load.
}
document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',async()=> {
    if(button.dataset.auditUrl) {
        button.disabled=true;
        try {
            const response=await fetch(button.dataset.auditUrl,{
                method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                body:JSON.stringify({report_token:button.dataset.reportToken})
            });
            if(!response.ok) throw new Error('audit failed');
        } catch(error) { window.alert(document.body.dataset.printError);return; }
        finally { button.disabled=false; }
    }
    window.print();
}));
document.querySelectorAll('[data-confirm-delete]').forEach(form=>form.addEventListener('submit',event=> {
    if(!window.confirm(form.dataset.confirmMessage)) event.preventDefault();
}));

// Values remain only in the existing field. Submission and page hiding restore masking.
const passwordControls = [];
document.querySelectorAll('[data-password-toggle]').forEach(button => {
    const input = button.parentElement.querySelector('input');
    if (!input) return;
    const setVisible = visible => {
        input.type = visible ? 'text' : 'password';
        const label = visible ? button.dataset.hideLabel : button.dataset.showLabel;
        button.setAttribute('aria-label', label);
        button.title = label;
        button.setAttribute('aria-pressed', String(visible));
        button.querySelector('i').className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
    };
    button.hidden = false;
    button.addEventListener('click', () => {
        setVisible(input.type === 'password');
        input.focus({preventScroll: true});
    });
    input.form?.addEventListener('submit', () => setVisible(false));
    passwordControls.push(() => setVisible(false));
});
window.addEventListener('pagehide', () => passwordControls.forEach(hide => hide()));
document.addEventListener('visibilitychange', () => {
    if(document.hidden) passwordControls.forEach(hide => hide());
});
