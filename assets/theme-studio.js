document.addEventListener('DOMContentLoaded',()=>{
 const root=document.querySelector('#erm_config');if(!root)return;
 const get=k=>root.querySelector('[name="erm['+k+']"]');
 const keys=['accent','background','text_color','label_bg','label_color','heading_color','font'];
 if(!keys.every(k=>get(k)))return;
 const presets=[
  {name:'Classic',colors:['#603f91','#ffffff','#222222','#ffffff','#222222','#222222'],font:'inherit'},
  {name:'Mardi Gras',colors:['#663399','#fffdf4','#272035','#e6d79b','#35264b','#4a246a'],font:'Georgia, serif'},
  {name:'Modern Blue',colors:['#1765b5','#f7fbff','#1c2938','#e9f3ff','#183b63','#1765b5'],font:'Arial, sans-serif'},
  {name:'Forest',colors:['#276749','#f9fcf8','#20372b','#e6f2e8','#1c4833','#276749'],font:'Georgia, serif'},
  {name:'Minimal',colors:['#292929','#ffffff','#202020','#f3f3f3','#333333','#202020'],font:'Arial, sans-serif'}
 ];
 const panel=document.createElement('section');panel.className='fcrc-theme-studio';
 const title=document.createElement('h3');title.textContent='Quick appearance studio';panel.append(title);
 const help=document.createElement('p');help.textContent='Choose a preset, customize individual colors, and preview how the form will look. Presets only change appearance settings and are not saved until you update the form.';panel.append(help);
 const chips=document.createElement('div');chips.className='fcrc-theme-presets';
 function refresh(){keys.forEach(k=>get(k)?.dispatchEvent(new Event('input',{bubbles:true})));paint()}
 presets.forEach(p=>{const b=document.createElement('button');b.type='button';b.className='button fcrc-theme-preset';b.innerHTML='<span class="fcrc-theme-dots">'+p.colors.slice(0,4).map(c=>'<i style="background:'+c+'"></i>').join('')+'</span>'+p.name;b.addEventListener('click',()=>{keys.slice(0,6).forEach((k,i)=>{get(k).value=p.colors[i];get(k).dispatchEvent(new Event('change',{bubbles:true}))});get('font').value=p.font;refresh()});chips.append(b)});panel.append(chips);
 const fontLabel=document.createElement('label');fontLabel.textContent='Font family ';const fonts=document.createElement('select');fonts.setAttribute('aria-label','Font family');
 [['inherit','Use website font'],['Arial, sans-serif','Arial'],['Georgia, serif','Georgia'],['Verdana, sans-serif','Verdana'],['Tahoma, sans-serif','Tahoma'],['Trebuchet MS, sans-serif','Trebuchet MS'],['Times New Roman, serif','Times New Roman'],['system-ui','System UI']].forEach(([v,l])=>{const o=document.createElement('option');o.value=v;o.textContent=l;fonts.append(o)});
 fonts.value=get('font').value||'inherit';if(![...fonts.options].some(o=>o.value===fonts.value)){const o=document.createElement('option');o.value=get('font').value;o.textContent='Current custom font';fonts.prepend(o);fonts.value=o.value}
 fonts.addEventListener('change',()=>{get('font').value=fonts.value;refresh()});get('font').addEventListener('input',()=>{fonts.value=get('font').value;paint()});fontLabel.append(fonts);panel.append(fontLabel);
 const heading=document.createElement('h4');heading.textContent='Responsive appearance preview';panel.append(heading);
 const sizes=document.createElement('div');sizes.className='fcrc-theme-sizes';
 const frame=document.createElement('div');frame.className='fcrc-theme-frame';frame.innerHTML='<div class="fcrc-theme-sample"><h3>Registration preview</h3><p>See how your colors and typography work together.</p><label>Full name</label><input type="text" placeholder="Your name" disabled><label>Email address</label><input type="text" placeholder="you@example.com" disabled><button type="button" disabled>Submit registration</button></div>';
 [['Desktop','desktop'],['Tablet','tablet'],['Mobile','mobile']].forEach(([name,v])=>{const b=document.createElement('button');b.type='button';b.className='button';b.textContent=name;b.setAttribute('aria-pressed',String(v==='desktop'));b.addEventListener('click',()=>{frame.dataset.size=v;[...sizes.children].forEach(x=>x.setAttribute('aria-pressed',String(x===b)))});sizes.append(b)});panel.append(sizes,frame);frame.dataset.size='desktop';
 function paint(){const sample=frame.firstElementChild;sample.style.setProperty('--preview-accent',get('accent').value);sample.style.background=get('background').value;sample.style.color=get('text_color').value;sample.style.fontFamily=get('font').value;sample.querySelectorAll('h3').forEach(x=>x.style.color=get('heading_color').value);sample.querySelectorAll('label').forEach(x=>{x.style.color=get('label_color').value;x.style.background=get('label_bg').value})}
 keys.forEach(k=>get(k).addEventListener('input',paint));paint();
 const appearance=[...root.querySelectorAll('h3')].find(x=>/Appearance/i.test(x.textContent));if(appearance)appearance.after(panel);else root.prepend(panel);
});