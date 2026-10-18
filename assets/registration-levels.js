document.addEventListener('DOMContentLoaded',()=>{
 const rows=document.getElementById('fcrc-level-rows'),add=document.getElementById('fcrc-add-level');if(!rows||!add)return;
 const create=()=>{const row=document.createElement('div');row.className='fcrc-level-row';
 const label=document.createElement('label');label.textContent='Level / Area ';const name=document.createElement('input');name.name='erm_levels[name][]';name.type='text';name.maxLength=120;name.required=true;name.placeholder='e.g. Area B';label.append(name);
 const priceLabel=document.createElement('label');priceLabel.textContent='Price ($) ';const price=document.createElement('input');price.name='erm_levels[price][]';price.type='number';price.min='0';price.max='10000000';price.step='0.01';price.value='0';priceLabel.append(price);
 const remove=document.createElement('button');remove.type='button';remove.className='button-link-delete fcrc-remove-level';remove.textContent='Remove';remove.addEventListener('click',()=>row.remove());row.append(label,priceLabel,remove);rows.append(row);name.focus()};
 add.addEventListener('click',create);rows.addEventListener('click',e=>{if(e.target.closest('.fcrc-remove-level'))e.target.closest('.fcrc-level-row').remove()});
});