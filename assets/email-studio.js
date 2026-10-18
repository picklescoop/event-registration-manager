document.addEventListener('DOMContentLoaded',()=>{
 const root=document.querySelector('#erm_config');if(!root)return;
 const field=name=>root.querySelector('[name="erm['+name+']"]');
 const keys=['organizer_email','admin_subject','admin_template','receipt','ack','customer_subject','customer_template'];
 const wrappers={};
 for(const key of keys){const el=field(key);if(!el)continue;const p=el.closest('p');if(p)wrappers[key]=p}
 if(!wrappers.admin_template||!wrappers.admin_subject)return;
 const section=document.createElement('section');section.className='fcrc-email-studio';
 const header=document.createElement('div');header.className='fcrc-email-header';
 header.innerHTML='<h3>Emails & confirmations</h3><p>Choose who receives notifications, customize the message, and see what will be sent. All changes save with the form.</p>';
 section.appendChild(header);
 const makePanel=(title,desc)=>{const box=document.createElement('div');box.className='fcrc-email-panel';const h=document.createElement('h4');h.textContent=title;box.appendChild(h);const p=document.createElement('p');p.textContent=desc;box.appendChild(p);section.appendChild(box);return box};
 const admin=makePanel('1. Organizer notification','Sent to your team when someone submits this form.');
 ['organizer_email','admin_subject','admin_template'].forEach(key=>{if(wrappers[key])admin.appendChild(wrappers[key])});
 const template=field('admin_template'),subject=field('admin_subject'),to=field('organizer_email');
 if(template){
  template.rows=7;
  const help=document.createElement('div');help.className='fcrc-email-tokens';
  const label=document.createElement('strong');label.textContent='Insert submission details';help.appendChild(label);
  const hint=document.createElement('p');hint.textContent='Click a button to insert a placeholder at the cursor. The actual submission values replace these when an email is sent.';help.appendChild(hint);
  const tokens=[['All form answers','{fields}'],['Appointment','{appointment}'],['Selected items','{items}'],['Total','{total}'],['Payment method','{payment}'],['Form name','{form_title}'],['Entry number','{entry_id}']];
  tokens.forEach(([label,token])=>{const b=document.createElement('button');b.type='button';b.className='button';b.textContent=label;b.title=token;b.addEventListener('click',()=>{const start=template.selectionStart??template.value.length,end=template.selectionEnd??start;template.setRangeText(token,start,end,'end');template.dispatchEvent(new Event('input',{bubbles:true}));template.focus()});help.appendChild(b)});
  template.closest('p').after(help);
 }
 const customer=makePanel('2. Customer confirmation','Choose whether visitors receive a copy of their submission, and what they see immediately after submitting.');
 ['receipt','customer_subject','customer_template','ack'].forEach(key=>{if(wrappers[key])customer.appendChild(wrappers[key])});
 if(wrappers.receipt){const desc=document.createElement('p');desc.className='description';desc.textContent='Enable the customer email above. Leave the customer message blank to use the original detailed receipt; otherwise your custom message is sent with appointment-management and ticket links automatically included.';wrappers.receipt.after(desc)}
 const customerTemplate=field('customer_template'),customerSubject=field('customer_subject');
 if(customerTemplate){customerTemplate.rows=7;const helper=document.createElement('div');helper.className='fcrc-email-tokens';helper.innerHTML='<strong>Insert into customer email</strong><p>Click to insert a submission placeholder at the cursor.</p>';[['Form name','{form_title}'],['Entry number','{entry_id}'],['All answers','{fields}'],['Appointment','{appointment}'],['Items','{items}'],['Total','{total}'],['Payment method','{payment}']].forEach(([label,token])=>{const b=document.createElement('button');b.type='button';b.className='button';b.textContent=label;b.addEventListener('click',()=>{customerTemplate.setRangeText(token,customerTemplate.selectionStart??customerTemplate.value.length,customerTemplate.selectionEnd??customerTemplate.value.length,'end');customerTemplate.dispatchEvent(new Event('input',{bubbles:true}));customerTemplate.focus()});helper.appendChild(b)});customerTemplate.closest('p').after(helper)}
 const preview=makePanel('3. Email preview','Choose which message to preview. Nothing is sent from this screen.');
 const mode=document.createElement('select');mode.innerHTML='<option value="customer">Customer confirmation</option><option value="organizer">Organizer notification</option>';mode.setAttribute('aria-label','Email preview type');preview.appendChild(mode);

 const mail=document.createElement('div');mail.className='fcrc-email-example';preview.appendChild(mail);
 const escape=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const render=()=>{
  const title=document.querySelector('#title')?.value||'Your form';
  const fields=[...root.querySelectorAll('input[name="erm_fields[label][]"]')].map(e=>e.value.trim()).filter(Boolean);
  const answers=(fields.length?fields:['Name','Email']).map(f=>f+': [sample answer]').join('\n');
  const items=[...root.querySelectorAll('input[name="erm_products[name][]"]')].map(e=>e.value.trim()).filter(Boolean);
  const itemText=items.length?items.slice(0,3).map(n=>n+' x 1 @ $[price]').join('\n'):'[selected products]';
  const appointment='[selected appointment]';
  const sampleBody=answers+'\n\nPayment selection: [payment method]\nPayment status: Pending verification\n\nRegistration received. Payment has not been verified.\n\nAppointment requested: '+appointment+'\nItems:\n'+itemText+'\nTotal: $[calculated total]\n';
  const values={'{form_title}':title,'{entry_id}':'1042','{fields}':sampleBody,'{appointment}':appointment,'{items}':itemText,'{total}':'$[calculated total]','{payment}':'[payment method]'};
  const fill=s=>String(s??'').replace(/\{(?:form_title|entry_id|fields|appointment|items|total|payment)\}/g,m=>values[m]||m);
  const isCustomer=mode.value==='customer';
  const defaultSubject='Submission received: '+title+' (#1042)';
  const subj=isCustomer?(customerSubject?.value.trim()?fill(customerSubject.value):defaultSubject):fill(subject?.value||'New submission: {form_title} #{entry_id}');
  const customCustomer=!!customerTemplate?.value.trim();
  let message=isCustomer?(customCustomer?fill(customerTemplate.value):sampleBody):fill(template?.value||'');
  if(isCustomer&&customCustomer){
   const formType=field('form_type')?.value;
   if(formType==='appointment')message+='\n\nManage your appointment: [private appointment link]';
   if(root.querySelector('[name="erm[tickets_enabled]"]')?.checked)message+='\n\nYour QR ticket links:\n[private ticket links]';
  }
  if(!isCustomer)message+='\n\nView and reply in dashboard: [admin submission link]';
  const enabled=field('receipt')?.checked!==false;
  mail.innerHTML='<div class="fcrc-email-example-head"><div><b>To:</b> '+escape(isCustomer?'customer@example.com':(to?.value||'your-email@example.com'))+'</div><div><b>Subject:</b> '+escape(subj)+'</div></div><pre>'+escape(message)+'</pre>';
  let note=preview.querySelector('.fcrc-email-preview-note');
  if(!note){note=document.createElement('p');note.className='description fcrc-email-preview-note';preview.appendChild(note)}
  note.textContent=isCustomer&&!enabled?'Customer receipt is disabled. Enable “Email the registrant a copy / receipt” to send this message.':'Preview uses sample submission data. Actual emails are plain text and may include additional appointment or ticket links.';
 };
 mode.addEventListener('change',render);
 // Delegate events so dynamic form fields and editor replacements also refresh the preview.
 root.addEventListener('input',render);
 root.addEventListener('change',render);
 document.querySelector('#title')?.addEventListener('input',render);
 // Refresh when a visual builder changes fields without dispatching input events.
 const fieldsArea=root.querySelector('#erm-fields')||root.querySelector('#erm-field-rows');
 if(fieldsArea){const observer=new MutationObserver(render);observer.observe(fieldsArea,{childList:true,subtree:true})}
 render();
 // Place the email editor in its own clear block while leaving all fields within the WordPress edit form.
 const appearance=[...root.querySelectorAll('h3')].find(h=>h.textContent.trim()==='Appearance & Notifications');
 if(appearance){appearance.textContent='Appearance';appearance.after(section)}else admin.before(section);
});