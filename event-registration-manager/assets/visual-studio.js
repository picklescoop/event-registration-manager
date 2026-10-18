/* Visual canvas for the existing WordPress form data model. No new database schema. */
document.addEventListener('DOMContentLoaded',()=>{
 const table=document.querySelector('#erm-fields');if(!table)return;
 const tbody=table.tBodies[0];const add=document.querySelector('#erm-add');
 const studio=document.createElement('section');studio.className='fcrc-visual-studio';
 studio.innerHTML='<header class="fcrc-studio-head"><div><strong>Visual Form Designer</strong><p>Click a field to edit it. Drag cards to change their order. Save with WordPress Update / Publish.</p></div><span class="fcrc-unsaved">Changes not yet saved</span></header><div class="fcrc-studio-grid"><aside class="fcrc-field-palette"><h3>Add a field</h3><div class="fcrc-palette-items"></div><button type="button" class="button" data-restore>Restore missing defaults</button></aside><div class="fcrc-canvas-area"><div class="fcrc-canvas-title">Form canvas <small>Drag ⠿ to reorder</small></div><div class="fcrc-canvas" aria-label="Editable form fields"></div><div class="fcrc-canvas-bottom"><button type="button" class="button button-primary" data-add>+ Add another field</button></div></div><aside class="fcrc-property-pane"><h3>Field properties</h3><p class="fcrc-select-hint">Select a field on the canvas to edit its properties.</p><div class="fcrc-properties" hidden><label>Field label<input data-prop="label" type="text"></label><label>Field ID<input data-prop="id" type="text" pattern="[a-z0-9_]+"></label><label>Field type<select data-prop="type"></select></label><label>Choices <small>(separate with |)</small><textarea data-prop="options" rows="3"></textarea></label><label>Field width<select data-prop="width"><option value="12">Full row</option><option value="6">Half row</option><option value="4">One third</option></select></label><label class="fcrc-check"><input data-prop="required" type="checkbox"> Required</label><label class="fcrc-check"><input data-prop="public" type="checkbox"> Show in public registration list</label><div class="fcrc-property-actions"><button type="button" class="button" data-duplicate>Duplicate</button><button type="button" class="button" data-delete>Delete</button></div></div></aside></div>';
 table.parentNode.insertBefore(studio,table);
 const canvas=studio.querySelector('.fcrc-canvas'),properties=studio.querySelector('.fcrc-properties');
 const types={text:'Text',email:'Email',tel:'Phone',number:'Number',textarea:'Long answer',select:'Dropdown',radio:'Radio choices',checkbox:'Checkboxes',date:'Date',file:'Image / file upload'};
 const escape=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let active=null,dragging=null;
 const rows=()=>Array.from(tbody.rows);
 const control=(tr,cls)=>tr.querySelector('.erm-'+cls)||tr.querySelector('[name^="erm_fields['+cls+']"]');
 const value=(tr,cls)=>control(tr,cls)?.value||'';
 const emit=el=>el.dispatchEvent(new Event('input',{bubbles:true}));
 function set(tr,cls,v){const el=control(tr,cls);if(!el)return;if(el.type==='checkbox')el.checked=!!v;else el.value=v;emit(el);}
 function uniqueId(label){let base=(label||'field').toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'')||'field';let id=base,i=2;while(rows().some(r=>value(r,'id')===id))id=base+'_'+i++;return id;}
 function renumber(){rows().forEach((tr,i)=>{control(tr,'required').name='erm_fields[required]['+i+']';control(tr,'public').name='erm_fields[public]['+i+']';});}
 function card(tr){const label=value(tr,'label')||'Untitled field';const type=value(tr,'type')||'text';const required=control(tr,'required').checked;
 const c=document.createElement('article');c.className='fcrc-field-card'+(tr===active?' selected':'');c.draggable=true;c.tabIndex=0;c.setAttribute('role','button');c.setAttribute('aria-label','Edit '+label);c._row=tr;
 let sample='<input type="text" disabled placeholder="Answer goes here">';
 if(type==='textarea')sample='<textarea disabled placeholder="Long answer"></textarea>';
 else if(type==='select')sample='<select disabled><option>Choose an option</option>'+value(tr,'options').split('|').filter(Boolean).map(o=>'<option>'+escape(o.trim())+'</option>').join('')+'</select>';
 else if(type==='checkbox'||type==='radio')sample='<span class="fcrc-choice-sample">'+(value(tr,'options').split('|').filter(Boolean).map(o=>(type==='radio'?'◯ ':'☐ ')+escape(o.trim())).join('　')||'Add choices in properties')+'</span>';
 else if(type==='file')sample='<div class="fcrc-upload-sample">⇧ Choose an image or file</div>';
 else if(['email','tel','date','number'].includes(type))sample='<input disabled type="'+type+'">';
 c.innerHTML='<div class="fcrc-card-top"><span class="fcrc-grip" title="Drag to reorder" aria-hidden="true">⠿</span><b>'+escape(label)+'</b>'+(required?'<span class="fcrc-asterisk">*</span>':'')+'<small>'+escape(types[type]||type)+'</small><span class="fcrc-reorder-actions"><button type="button" class="fcrc-up" aria-label="Move field up">↑</button><button type="button" class="fcrc-down" aria-label="Move field down">↓</button></span></div><div class="fcrc-sample">'+sample+'</div>';
 c.querySelector('.fcrc-up').addEventListener('click',e=>{e.stopPropagation();move(tr,-1);});c.querySelector('.fcrc-down').addEventListener('click',e=>{e.stopPropagation();move(tr,1);});c.addEventListener('click',()=>select(tr));c.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();select(tr);}if(e.altKey&&(e.key==='ArrowUp'||e.key==='ArrowDown')){e.preventDefault();move(tr,e.key==='ArrowUp'?-1:1);}});
 c.addEventListener('dragstart',e=>{dragging=tr;e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain','field');c.classList.add('dragging');});
 c.addEventListener('dragover',e=>{if(!dragging||dragging===tr)return;e.preventDefault();c.classList.add('drop-target');});
 c.addEventListener('dragleave',()=>c.classList.remove('drop-target'));
 c.addEventListener('drop',e=>{e.preventDefault();c.classList.remove('drop-target');if(!dragging||dragging===tr)return;const rect=c.getBoundingClientRect();tbody.insertBefore(dragging,e.clientY<rect.top+rect.height/2?tr:tr.nextSibling);renumber();render();});
 c.addEventListener('dragend',()=>{dragging=null;render();});return c;
 }
 function render(skipProperties=false){if(active&&!tbody.contains(active))active=null;canvas.replaceChildren(...rows().map(card));if(!rows().length)canvas.innerHTML='<p class="fcrc-empty">Your form is empty. Add a field from the left to get started.</p>';studio.querySelector('.fcrc-select-hint').hidden=!!active;properties.hidden=!active;if(active&&!skipProperties)populate();}
 function select(tr){active=tr;render();}
 function populate(){if(!active)return;['label','id','type','options','width'].forEach(k=>{const p=properties.querySelector('[data-prop="'+k+'"]');p.value=value(active,k);});['required','public'].forEach(k=>properties.querySelector('[data-prop="'+k+'"]').checked=control(active,k).checked);properties.querySelector('[data-prop="options"]').closest('label').hidden=!['select','radio','checkbox'].includes(value(active,'type'));}
 function addField(type='text'){add.click();const tr=rows().at(-1);if(!tr)return;set(tr,'type',type);const label=types[type]||'New field';set(tr,'label',label);set(tr,'id',uniqueId(label));renumber();select(tr);properties.querySelector('[data-prop="label"]').focus();}
 function move(tr,delta){const a=rows(),i=a.indexOf(tr),j=i+delta;if(j<0||j>=a.length)return;tbody.insertBefore(tr,delta<0?a[j]:a[j].nextSibling);renumber();render();}
 const palette=studio.querySelector('.fcrc-palette-items');Object.entries(types).forEach(([type,label])=>{const b=document.createElement('button');b.type='button';b.className='fcrc-palette-button';b.textContent='+ '+label;b.onclick=()=>addField(type);palette.appendChild(b);});
 const selectType=properties.querySelector('[data-prop="type"]');Object.entries(types).forEach(([k,v])=>selectType.add(new Option(v,k)));
 properties.addEventListener('input',e=>{if(!active)return;const p=e.target.dataset.prop;if(!p)return;set(active,p,e.target.type==='checkbox'?e.target.checked:e.target.value);render(true);});
 properties.addEventListener('change',e=>{if(!active)return;const p=e.target.dataset.prop;if(p){set(active,p,e.target.type==='checkbox'?e.target.checked:e.target.value);render();}});
 studio.querySelector('[data-add]').onclick=()=>addField();studio.querySelector('[data-restore]').onclick=()=>{document.querySelector('#erm-preset').click();renumber();render();};
 studio.querySelector('[data-delete]').onclick=()=>{if(!active||!window.confirm('Remove this field from the form?'))return;active.remove();active=null;renumber();render();emit(tbody);};
 studio.querySelector('[data-duplicate]').onclick=()=>{if(!active)return;const original=active;add.click();const tr=rows().at(-1);['label','type','options','width'].forEach(k=>set(tr,k,value(original,k)));set(tr,'id',uniqueId(value(original,'id')));['required','public'].forEach(k=>set(tr,k,control(original,k).checked));tbody.insertBefore(tr,original.nextSibling);renumber();select(tr);};
 tbody.addEventListener('input',()=>requestAnimationFrame(()=>render(properties.contains(document.activeElement))));tbody.addEventListener('change',()=>requestAnimationFrame(render));new MutationObserver(()=>{renumber();render();}).observe(tbody,{childList:true});
 // The table remains as the compatibility-backed form model but is visually replaced by the canvas.
 table.classList.add('fcrc-legacy-table');const oldButtons=[document.querySelector('#erm-add'),document.querySelector('#erm-preset')];oldButtons.forEach(b=>{if(b)b.classList.add('fcrc-legacy-button');});
 render();
});
