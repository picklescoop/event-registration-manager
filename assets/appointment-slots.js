document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('.erm-appointment-settings').forEach(el=>{
  let cfg;try{cfg=JSON.parse(el.textContent)}catch(e){return}
  const form=el.closest('form')||document.querySelector('form:has([name="erm_appointment_date"])')||document.querySelector('form:has([name="appointment_action"][value="reschedule"])');
  if(!form)return;
  const date=form.querySelector('[name="erm_appointment_date"],[name="date"]'),time=form.querySelector('[name="erm_appointment_time"],[name="time"]'),feedback=form.querySelector('.erm-slot-feedback');
  if(!date||!time)return;
  const panel=date.closest('.erm-appointment-request');
  let calendar=null,days=null,monthTitle=null,prev=null,next=null,slotsPanel=null,selected=null,visibleMonth=null;
  const dateString=d=>[d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
  const parseDate=v=>{const p=v.split('-').map(Number);return new Date(p[0],p[1]-1,p[2])};
  const formatTime=t=>{const p=t.split(':').map(Number);return new Date(2000,0,1,p[0],p[1]).toLocaleTimeString([],{hour:'numeric',minute:'2-digit'})};
  if(panel){
   panel.classList.add('erm-calendar-enhanced');
   const dateLabel=date.closest('label'),timeLabel=time.closest('label');
   if(dateLabel)dateLabel.classList.add('erm-native-picker');
   if(timeLabel)timeLabel.classList.add('erm-native-picker');
   calendar=document.createElement('div');calendar.className='erm-booking-layout';
   calendar.innerHTML='<div class="erm-booking-calendar"><div class="erm-booking-topline"><div><span class="erm-booking-eyebrow">Step 1 · Select a date</span><h3>Choose a day</h3></div><div class="erm-booking-nav"><button type="button" class="erm-month-prev" aria-label="Previous month">‹</button><button type="button" class="erm-month-next" aria-label="Next month">›</button></div></div><div class="erm-booking-month" aria-live="polite"></div><div class="erm-booking-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div><div class="erm-booking-days" role="group" aria-label="Select appointment date"></div></div><div class="erm-booking-times"><span class="erm-booking-eyebrow">Step 2 · Select a time</span><h3 class="erm-booking-selected">Choose a date first</h3><div class="erm-booking-time-list" role="group" aria-label="Available appointment times"><p class="erm-booking-placeholder">Available times will appear here after you choose a date.</p></div></div>';
   panel.insertBefore(calendar,dateLabel||panel.firstChild);
   days=calendar.querySelector('.erm-booking-days');monthTitle=calendar.querySelector('.erm-booking-month');prev=calendar.querySelector('.erm-month-prev');next=calendar.querySelector('.erm-month-next');slotsPanel=calendar.querySelector('.erm-booking-time-list');selected=calendar.querySelector('.erm-booking-selected');
   const min=date.min?parseDate(date.min):new Date(),max=date.max?parseDate(date.max):new Date(min.getFullYear()+1,min.getMonth(),min.getDate());
   visibleMonth=new Date(min.getFullYear(),min.getMonth(),1);
   const render=()=>{
    const first=new Date(visibleMonth.getFullYear(),visibleMonth.getMonth(),1),count=new Date(visibleMonth.getFullYear(),visibleMonth.getMonth()+1,0).getDate();
    monthTitle.textContent=first.toLocaleDateString([],{month:'long',year:'numeric'});
    days.replaceChildren();for(let i=0;i<first.getDay();i++){const blank=document.createElement('span');blank.className='erm-booking-blank';days.appendChild(blank)}
    for(let i=1;i<=count;i++){const d=new Date(first.getFullYear(),first.getMonth(),i),v=dateString(d),b=document.createElement('button');b.type='button';b.className='erm-booking-day';b.textContent=i;b.setAttribute('aria-label',d.toLocaleDateString([],{weekday:'long',month:'long',day:'numeric',year:'numeric'}));b.disabled=d<min||d>max;if(v===date.value){b.classList.add('is-selected');b.setAttribute('aria-pressed','true')}else b.setAttribute('aria-pressed','false');b.addEventListener('click',()=>{date.value=v;date.dispatchEvent(new Event('change',{bubbles:true}));render()});days.appendChild(b)}
    prev.disabled=first<=new Date(min.getFullYear(),min.getMonth(),1);next.disabled=first>=new Date(max.getFullYear(),max.getMonth(),1);
   };
   prev.addEventListener('click',()=>{visibleMonth.setMonth(visibleMonth.getMonth()-1);render()});next.addEventListener('click',()=>{visibleMonth.setMonth(visibleMonth.getMonth()+1);render()});render();
  }
  let token=0;
  date.addEventListener('change',async()=>{
   const seq=++token;time.innerHTML='<option value="">Loading available times...</option>';feedback.textContent='';
   if(slotsPanel)slotsPanel.innerHTML='<p class="erm-booking-placeholder">Checking availability…</p>';
   if(selected)selected.textContent=date.value?parseDate(date.value).toLocaleDateString([],{weekday:'long',month:'long',day:'numeric'}):'Choose a date first';
   if(!date.value)return;
   try{
    const url=new URL(cfg.ajax);url.searchParams.set('action','erm_available_slots');url.searchParams.set('event',cfg.event);url.searchParams.set('date',date.value);url.searchParams.set('nonce',cfg.nonce);
    const response=await fetch(url.toString(),{credentials:'same-origin'});const result=await response.json();if(seq!==token)return;
    const slots=result.success?result.data.slots:[];time.innerHTML='<option value="">Select a time</option>';
    if(slotsPanel)slotsPanel.replaceChildren();
    slots.forEach(t=>{const o=document.createElement('option');o.value=t;o.textContent=formatTime(t);time.appendChild(o);
     if(slotsPanel){const b=document.createElement('button');b.type='button';b.className='erm-booking-time';b.textContent=formatTime(t);b.setAttribute('aria-pressed','false');b.addEventListener('click',()=>{time.value=t;time.dispatchEvent(new Event('change',{bubbles:true}))});slotsPanel.appendChild(b)}
    });
    if(slotsPanel&&!slots.length)slotsPanel.innerHTML='<p class="erm-booking-placeholder">No available times on this date. Please choose another day.</p>';
    feedback.textContent=slots.length?'Select an available time to continue.':'No available times on this date.';
   }catch(e){time.innerHTML='<option value="">Unable to load times</option>';feedback.textContent='Please try another date.';if(slotsPanel)slotsPanel.innerHTML='<p class="erm-booking-placeholder">Unable to load availability. Try another date.</p>'}
  });
  time.addEventListener('change',()=>{if(!slotsPanel)return;slotsPanel.querySelectorAll('.erm-booking-time').forEach(b=>{const active=b.textContent===time.selectedOptions[0]?.textContent;b.classList.toggle('is-selected',active);b.setAttribute('aria-pressed',String(active))});if(time.value)feedback.textContent='Selected: '+(selected?selected.textContent+' · ':'')+formatTime(time.value)});
 });
});
