document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('.fcrc-shippo-live').forEach(widget=>{
  const form=widget.closest('form'),fieldset=widget.closest('.fcrc-fulfillment');if(!form||!fieldset)return;
  const button=widget.querySelector('.fcrc-shippo-quote-button'),feedback=widget.querySelector('.fcrc-shippo-feedback'),list=widget.querySelector('.fcrc-shippo-rates');
  const addressKeys=['country','postcode','street','city','region'];const fields=addressKeys.map(k=>form.querySelector('[name="erm_ship_'+k+'"]'));
  const hidden=(name)=>{const el=document.createElement('input');el.type='hidden';el.name=name;form.append(el);return el};
  const token=hidden('erm_shippo_quote_token'),rateId=hidden('erm_shippo_rate_id');
  let chosen=null;
  const money=n=>new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(n);
  function updateTotal(){const subtotal=Number((form.querySelector('.fcrc-cart-total')?.textContent||'0').replace(/[^\d.]/g,''))||0;const ship=fieldset.querySelector('input[name=erm_fulfillment]:checked')?.value==='shipping';const amount=ship&&chosen?chosen.amount:0;const estimate=fieldset.querySelector('.fcrc-shipping-estimate');if(estimate)estimate.textContent=ship?(chosen?money(amount):'Choose a live shipping rate'):'$0.00';const grand=form.querySelector('.fcrc-cart-grand-value');if(grand)grand.textContent=chosen||!ship?money(subtotal+amount):'Choose shipping rate';}
  const invalidate=()=>{chosen=null;token.value='';rateId.value='';list.replaceChildren();feedback.textContent='Enter your address and request current shipping rates.';updateTotal()};
  fields.forEach(el=>el?.addEventListener('input',invalidate));fieldset.querySelectorAll('input[name=erm_fulfillment]').forEach(el=>el.addEventListener('change',updateTotal));
  const total=form.querySelector('.fcrc-cart-total');if(total)new MutationObserver(updateTotal).observe(total,{childList:true,subtree:true,characterData:true});
  button.addEventListener('click',async()=>{
   invalidate();if(fields.some(el=>!el?.value.trim())){feedback.textContent='Complete the delivery address first.';return}
   button.disabled=true;feedback.textContent='Checking live carrier prices…';
   const payload=new URLSearchParams({action:'erm_shippo_rates',form_id:widget.dataset.form,nonce:widget.dataset.nonce});addressKeys.forEach((key,i)=>payload.set('erm_ship_'+key,fields[i].value));
   try{const response=await fetch(widget.dataset.ajax,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:payload.toString()});const result=await response.json();if(!result.success)throw new Error(result.data?.message||'Unable to retrieve shipping rates');token.value=result.data.token;feedback.textContent='Choose your shipping service:';
    result.data.rates.forEach(rate=>{const label=document.createElement('label');label.className='fcrc-shippo-rate';const radio=document.createElement('input');radio.type='radio';radio.name='erm_shippo_rate_choice';radio.value=rate.id;radio.addEventListener('change',()=>{chosen=rate;rateId.value=rate.id;updateTotal()});const text=document.createElement('span');text.textContent=' '+rate.carrier+' — '+rate.service+' — '+money(rate.amount)+(rate.days?' (approx. '+rate.days+' days)':'');label.append(radio,text);list.append(label)})
   }catch(err){feedback.textContent=err.message||'Shipping service unavailable. Please try again.'}finally{button.disabled=false}
  });
  form.addEventListener('submit',e=>{if(fieldset.querySelector('input[name=erm_fulfillment]:checked')?.value==='shipping'&&!chosen){e.preventDefault();feedback.textContent='Choose a live shipping rate before submitting your order.';button.focus()}});
  invalidate();
 });
});
