document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('.fcrc-fulfillment').forEach(fieldset=>{
  const form=fieldset.closest('form');if(!form)return;
  const box=fieldset.querySelector('.fcrc-shipping-address'),estimate=fieldset.querySelector('.fcrc-shipping-estimate');
  if(!box)return;
  const country=box.querySelector('[name=erm_ship_country]'),zip=box.querySelector('[name=erm_ship_postcode]');
  const inputs=[...box.querySelectorAll('input')];
  let rules=[];try{rules=JSON.parse(fieldset.dataset.rules||'[]')}catch(e){}
  const fallback=Number(fieldset.dataset.default||0);
  const money=v=>new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(v);
  function quote(){const c=country.value.toUpperCase().replace(/[^A-Z]/g,''),z=zip.value.toUpperCase().replace(/[^A-Z0-9]/g,'');let score=-1,amount=fallback;for(const r of rules){const rc=(r.country||'').toUpperCase(),prefix=(r.prefix||'').toUpperCase();if(rc&&rc!==c)continue;if(prefix&&!z.startsWith(prefix))continue;const rank=prefix.length*10+(rc?1:0);if(rank>score){score=rank;amount=Number(r.price)||0}}return Math.max(0,amount)}
  function update(){const chosen=form.querySelector('input[name=erm_fulfillment]:checked')?.value;const ship=chosen==='shipping';box.hidden=!ship;inputs.forEach(i=>i.required=ship);const amount=ship?(fieldset.dataset.live==='1'?0:quote()):0;if(fieldset.dataset.live!=='1')estimate.textContent=money(amount);const summary=form.querySelector('.fcrc-cart-summary');if(summary){let line=summary.querySelector('.fcrc-cart-shipping-line');if(!line){line=document.createElement('p');line.className='fcrc-cart-shipping-line';summary.append(line)}line.textContent=ship?'Estimated shipping: '+money(amount):'Pickup: free';const subtotal=summary.querySelector('.fcrc-cart-total');let total=summary.querySelector('.fcrc-cart-grand-total');if(!total){total=document.createElement('p');total.innerHTML='<strong>Estimated order total: <span class="fcrc-cart-grand-value"></span></strong>';summary.append(total)}const base=Number((subtotal?.textContent||'0').replace(/[^0-9.]/g,''))||0;if(fieldset.dataset.live!=='1')total.querySelector('span').textContent=money(base+amount)}}
  fieldset.querySelectorAll('input').forEach(i=>i.addEventListener('input',update));fieldset.querySelectorAll('input[name=erm_fulfillment]').forEach(i=>i.addEventListener('change',update));
  const observer=new MutationObserver(update);const total=form.querySelector('.fcrc-cart-total');if(total)observer.observe(total,{childList:true,characterData:true,subtree:true});
  update();
 });
});