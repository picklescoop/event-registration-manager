document.addEventListener('DOMContentLoaded',()=>{
 const root=document.querySelector('#erm_config');if(!root)return;
 const type=root.querySelector('[name="erm[form_type]"]');if(!type)return;
 const studio=root.querySelector('.fcrc-visual-studio'),table=root.querySelector('#erm-fields');
 // Preserve the original inputs in the DOM: hidden sections must still save their settings.
 if(table){
   const legacy=document.createElement('details');legacy.className='fcrc-advanced-fields';
   const summary=document.createElement('summary');summary.textContent='Advanced: edit field IDs and raw field settings';
   legacy.appendChild(summary);table.before(legacy);legacy.appendChild(table);
   const add=root.querySelector('#erm-add'),restore=root.querySelector('#erm-preset');
   if(add)legacy.appendChild(add);
   if(restore)legacy.appendChild(restore);
 }
 // Form sections may be nested inside the existing step navigation. Wrap related
 // content, without cloning inputs or moving them out of the WordPress edit form.
 const headings=[...root.querySelectorAll('h3')];
 const category=h=>{
   const t=h.textContent.trim().toLowerCase();
   if(t.includes('appointment scheduling'))return 'appointment';
   if(t==='qr tickets')return 'tickets';
   if(t==='payment')return 'payment';
   if(t.includes('products')||t.includes('catalog'))return 'catalog';
   return null;
 };
 const sections=[];
 for(const h of headings){
   const cat=category(h);if(!cat)continue;
   const items=[h];let node=h.nextSibling;
   while(node){
     if(node.nodeType===1&&/^H[23]$/.test(node.tagName))break;
     const next=node.nextSibling;items.push(node);node=next;
   }
   const wrap=document.createElement('section');wrap.className='fcrc-context-section fcrc-context-'+cat;
   h.before(wrap);items.forEach(item=>wrap.appendChild(item));
   sections.push({cat,wrap});
 }
 const show=()=>{
   const v=type.value;
   sections.forEach(({cat,wrap})=>{
     const visible=cat==='appointment'?v==='appointment':cat==='tickets'?['registration','shop'].includes(v):cat==='catalog'?v==='shop':cat==='payment'?v!=='contact':true;
     wrap.hidden=!visible;
   });
   const notice=root.querySelector('.fcrc-type-context');
   if(notice){
     notice.textContent=({
       appointment:'Appointment form: set available days, hours, and booking rules in Scheduling.',
       contact:'Contact form: only questions, uploads, appearance, and notifications are needed.',
       shop:'Sales form: configure products, payment options, and tickets as needed.',
       registration:'Registration form: customize fields, payment, and optional tickets.'
     })[v]||'';
   }
 };
 const note=document.createElement('p');note.className='fcrc-type-context';type.after(note);
 type.addEventListener('change',show);show();
 if(studio){
   studio.classList.add('fcrc-primary-designer');
   const hint=document.createElement('p');hint.className='fcrc-design-hint';
   hint.textContent='Build your form visually below. Select any field to edit its properties; the advanced table is optional.';
   studio.before(hint);
 }
});