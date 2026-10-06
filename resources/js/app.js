import '@tabler/core/dist/js/tabler.min.js';
// Each count field stays visible with an accurate label when JavaScript is unavailable.
const type = document.querySelector('[data-exam-type]');
if(type) {
    const toggleCounts = () => {
        const viva = type.selectedOptions[0]?.dataset.viva === '1';
        for(const kind of ['center','board']) {
            const field = document.querySelector(`[data-count="${kind}"]`);
            if(!field) continue;
            const show = Boolean(type.value) && (kind === 'board' ? viva : !viva);
            field.hidden = !show;
            for(const input of field.querySelectorAll('input')) { input.disabled = !show; input.required = show && kind === 'board'; }
        }
    };
    type.addEventListener('change',toggleCounts); toggleCounts();
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
