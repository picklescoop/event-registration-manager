document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('.fcrc-cart-summary').forEach(summary=>{
  const form=summary.closest('form');if(!form)return;
  const inputs=[...form.querySelectorAll('.fcrc-cart-catalog input[name^="erm_qty["]')];
  const labels=inputs.map(i=>i.closest('label'));
  const names=labels.map(l=>(l?.querySelector('strong')?.textContent||'Item').trim());
  const prices=labels.map(l=>{const text=l?.textContent||'';const m=text.match(/\$([\d,]+(?:\.\d{1,2})?)/);return m?Number(m[1].replaceAll(',','')):0});
  const lines=summary.querySelector('.fcrc-cart-lines'),total=summary.querySelector('.fcrc-cart-total'),empty=summary.querySelector('.fcrc-cart-empty');
  const money=n=>new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(n);
  const draw=()=>{let sum=0,html=[];inputs.forEach((input,i)=>{const qty=Math.max(0,Math.min(100,Number.parseInt(input.value,10)||0));if(qty){const amount=qty*prices[i];sum+=amount;const row=document.createElement('div');row.className='fcrc-cart-line';const name=document.createElement('span');name.textContent=names[i]+' × '+qty;const value=document.createElement('strong');value.textContent=money(amount);row.append(name,value);html.push(row)}});lines.replaceChildren(...html);if(!html.length)lines.textContent='Choose products above to build your cart.';total.textContent=money(sum);empty.hidden=html.length>0};
  inputs.forEach(i=>i.addEventListener('input',draw));draw();
  form.addEventListener('submit',e=>{if(!inputs.some(i=>Number(i.value)>0)){e.preventDefault();empty.hidden=false;inputs[0]?.focus()}});
 });
});