document.addEventListener('DOMContentLoaded',()=>{
 const root=document.querySelector('#erm_config .inside');if(!root)return;
 const type=root.querySelector('[name="erm[form_type]"]');if(!type)return;
 const children=Array.from(root.children);const blocks=[];let current=null;
 const create=(title)=>{const section=document.createElement('section');section.className='erm-ui-section';section.dataset.section=title;blocks.push(section);root.appendChild(section);const h=document.createElement('h3');h.textContent=title;section.appendChild(h);return section;};
 current=create('Form Fields');
 children.forEach(node=>{if(node.tagName==='H3'){const title=node.textContent.trim();current=create(title);node.remove();}else current.appendChild(node);});
 const intro=document.createElement('div');intro.className='erm-builder-intro';intro.innerHTML='<span class="erm-kicker">FORM BUILDER STUDIO</span><h2>Design a form, without the clutter.</h2><p>Choose a form type, edit fields visually, and configure only the features you need. Use WordPress Update or Publish to save your changes.</p>';
 root.prepend(intro);
 // The WordPress post title is the canonical form name; never save a second copy.
 const wpTitle=document.querySelector('#title');
 const namePanel=document.createElement('div');namePanel.className='erm-form-name-panel';
 const nameLabel=document.createElement('label');nameLabel.setAttribute('for','erm-form-name');nameLabel.textContent='Form name';
 const nameInput=document.createElement('input');nameInput.type='text';nameInput.id='erm-form-name';nameInput.className='erm-form-name-input';nameInput.placeholder='e.g. Vendor Registration, Appointment Booking, Contact Us';nameInput.autocomplete='off';
 nameInput.value=wpTitle?.value||'';
 const nameHint=document.createElement('p');nameHint.textContent='Give your form a recognizable name. This is also the title shown in the Forms list.';
 namePanel.append(nameLabel,nameInput,nameHint);intro.after(namePanel);
 if(wpTitle){
   wpTitle.closest('#titlediv')?.classList.add('erm-native-title-mirrored');
   nameInput.addEventListener('input',()=>{wpTitle.value=nameInput.value;wpTitle.dispatchEvent(new Event('input',{bubbles:true}));wpTitle.dispatchEvent(new Event('change',{bubbles:true}));});
   wpTitle.addEventListener('input',()=>{if(document.activeElement!==nameInput)nameInput.value=wpTitle.value;});
   const editorForm=document.querySelector('#post');
   editorForm?.addEventListener('submit',e=>{if(!nameInput.value.trim()){e.preventDefault();nameInput.setCustomValidity('Please name your form before saving.');nameInput.reportValidity();nameInput.focus();}else nameInput.setCustomValidity('');});
   nameInput.addEventListener('input',()=>nameInput.setCustomValidity(''));
 }

 const chooser=document.createElement('div');chooser.className='erm-type-chooser';chooser.innerHTML='<label for="erm-builder-type">What are you building?</label><p>Changing the type updates the available settings without deleting your existing data.</p>';
 const chooserSelect=document.createElement('select');chooserSelect.id='erm-builder-type';Array.from(type.options).forEach(o=>chooserSelect.add(new Option(o.text,o.value)));chooserSelect.value=type.value;chooser.appendChild(chooserSelect);root.insertBefore(chooser,intro.nextSibling);
 const nav=document.createElement('nav');nav.className='erm-step-nav';nav.setAttribute('aria-label','Form builder settings');root.insertBefore(nav,chooser.nextSibling);
 const footer=document.createElement('div');footer.className='erm-step-footer';footer.innerHTML='<button type="button" class="button erm-prev">← Previous</button><span class="erm-step-count" aria-live="polite"></span><button type="button" class="button button-primary erm-next">Next section →</button>';root.appendChild(footer);
 const meta={
  'Form Fields':{label:'Visual Fields',icon:'▦'},
  'Appointment Scheduling':{label:'Scheduling',icon:'◷',types:['appointment']},
  'Products, Tickets & Service Levels':{label:'Products & Services',icon:'▣',types:['shop']},
  'Appearance & Notifications':{label:'Appearance & Emails',icon:'◈'},
  'Payment':{label:'Payments',icon:'＄',types:['registration','shop','appointment']},
  'QR Tickets':{label:'QR Tickets',icon:'▦',types:['shop','registration']},
  'Uploads':{label:'Uploads & Privacy',icon:'▧'}
 };
 const buttons=new Map();let active='Form Fields';
 blocks.forEach(block=>{const name=block.dataset.section;if(name==='Form type'){block.hidden=true;return;}const m=meta[name]||{label:name,icon:'•'};const button=document.createElement('button');button.type='button';button.className='erm-step-button';button.innerHTML='<span class="erm-step-num" aria-hidden="true">'+m.icon+'</span><span><strong></strong><small></small></span>';button.querySelector('strong').textContent=m.label;button.querySelector('small').textContent=name==='Appointment Scheduling'?'Availability, duration and time slots':name==='Form Fields'?'Click fields to edit; drag to reorder':name;button.addEventListener('click',()=>{active=name;refresh();});nav.appendChild(button);buttons.set(name,button);});
 function visible(){return blocks.filter(b=>{if(b.dataset.section==='Form type')return false;const m=meta[b.dataset.section];return !m?.types||m.types.includes(type.value);});}
 function refresh(){const shown=visible();if(!shown.some(b=>b.dataset.section===active))active=shown[0]?.dataset.section||'';
 blocks.forEach(b=>{const allowed=shown.includes(b);b.hidden=!allowed||b.dataset.section!==active;const button=buttons.get(b.dataset.section);if(!button)return;button.hidden=!allowed;button.classList.toggle('is-active',allowed&&b.dataset.section===active);button.setAttribute('aria-current',allowed&&b.dataset.section===active?'step':'false');});
 const i=shown.findIndex(b=>b.dataset.section===active);footer.querySelector('.erm-step-count').textContent='Section '+(i+1)+' of '+shown.length;footer.querySelector('.erm-prev').disabled=i<=0;footer.querySelector('.erm-next').disabled=i>=shown.length-1;
 }
 function navigate(n){const shown=visible();const i=shown.findIndex(b=>b.dataset.section===active);active=shown[Math.max(0,Math.min(shown.length-1,i+n))]?.dataset.section||active;refresh();root.scrollIntoView({behavior:'smooth',block:'start'});}
 footer.querySelector('.erm-prev').addEventListener('click',()=>navigate(-1));footer.querySelector('.erm-next').addEventListener('click',()=>navigate(1));
 chooserSelect.addEventListener('change',()=>{type.value=chooserSelect.value;type.dispatchEvent(new Event('change',{bubbles:true}));active=chooserSelect.value==='appointment'?'Appointment Scheduling':chooserSelect.value==='shop'?'Products, Tickets & Service Levels':'Form Fields';refresh();});
 type.addEventListener('change',()=>{chooserSelect.value=type.value;refresh();});
 // Keep the underlying table as the save data model, but replace it with the visual canvas.
 const table=root.querySelector('#erm-fields');if(table){const note=document.createElement('p');note.className='fcrc-legacy-note';note.textContent='Fields are edited in the visual canvas above. The legacy table remains available for compatibility.';table.before(note);}
 refresh();
});
document.addEventListener('click',function(e){if(!e.target.closest('.erm-bg-media')||!window.wp||!wp.media)return;e.preventDefault();const frame=wp.media({title:'Choose form background image',multiple:false,library:{type:'image'}});frame.on('select',()=>{const input=document.querySelector('[name="erm[background_image]"]');if(!input)return;input.value=frame.state().get('selection').first().toJSON().url;input.dispatchEvent(new Event('input',{bubbles:true}));});frame.open();});
