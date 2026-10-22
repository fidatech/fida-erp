(()=>{
 const shell=document.getElementById('fida-shell');
 const rail=[...document.querySelectorAll('[data-group]')];
 const sections=[...document.querySelectorAll('[data-nav-group]')];
 const pick=(section)=>{
   for(const r of rail){const active=r.dataset.group===section;r.classList.toggle('active',active);r.setAttribute('aria-pressed',String(active))}
   for(const s of sections)s.hidden=s.dataset.navGroup!==section;
 };
 for(const r of rail)r.addEventListener('click',()=>pick(r.dataset.group));
 const mobile=document.getElementById('fida-mobile-toggle');
 mobile?.addEventListener('click',()=>{let on=shell.classList.toggle('mobile-open');mobile.setAttribute('aria-expanded',String(on))});
 const drawer=document.getElementById('fida-drawer');
 const shade=document.getElementById('fida-drawer-shade');
 const openDrawer=()=>{drawer?.classList.add('open');drawer?.setAttribute('aria-hidden','false');if(shade)shade.hidden=false};
 const closeDrawer=()=>{drawer?.classList.remove('open');drawer?.setAttribute('aria-hidden','true');if(shade)shade.hidden=true};
 document.getElementById('fida-actions-toggle')?.addEventListener('click',openDrawer);
 document.getElementById('fida-drawer-close')?.addEventListener('click',closeDrawer);
 shade?.addEventListener('click',closeDrawer);
 document.getElementById('fida-alert-toggle')?.addEventListener('click',()=>{document.getElementById('fida-alert-box')?.remove();const e=document.createElement('div');e.id='fida-alert-box';e.className='fida-toast';e.textContent='Notifications are not configured for this workspace yet.';document.body.appendChild(e);setTimeout(()=>e.remove(),3500)});
 const search=document.getElementById('fida-global-search');
 const result=document.getElementById('fida-search-results');
 const links=window.FidaERP?.searchItems??[];
 const safe=(s)=>String(s).replace(/[&<>"']/g,(c)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 search?.addEventListener('input',()=>{
   const q=search.value.toLowerCase().trim();
   if(!result)return;
   const items=q?links.filter(x=>(x.label+' '+x.group).toLowerCase().includes(q)).slice(0,10):[];
   result.replaceChildren();
   for(const item of items){const a=document.createElement('a');a.href=item.url;a.textContent=item.group+' / '+item.label;result.appendChild(a)}
   if(q&&!items.length){const empty=document.createElement('span');empty.textContent='No matching module';result.appendChild(empty)}
   result.hidden=!q;
 });
 document.addEventListener('keydown',(e)=>{
  if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();search?.focus()}
  if(e.key==='Escape'){search?.blur();if(result)result.hidden=true;closeDrawer();shell?.classList.remove('mobile-open')}
 });
 document.addEventListener('click',e=>{
  if(!e.target.closest('.fida-search')&&result)result.hidden=true;
  const tab=e.target.closest('[data-tab]');
  if(tab){const key=tab.dataset.tab;const parent=tab.closest('[data-tabs-root]');if(parent){parent.querySelectorAll('[data-tab]').forEach(t=>t.classList.toggle('active',t===tab));parent.querySelectorAll('[data-tab-panel]').forEach(p=>p.hidden=p.dataset.tabPanel!==key)}}
  const modalOpen=e.target.closest('[data-open-modal]');
  if(modalOpen){const modal=document.getElementById(modalOpen.dataset.openModal);modal?.showModal?.()}
  const modalClose=e.target.closest('[data-close-modal]');
  if(modalClose)modalClose.closest('dialog')?.close();
 });
})();