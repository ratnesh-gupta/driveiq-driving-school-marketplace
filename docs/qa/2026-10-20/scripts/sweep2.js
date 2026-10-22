const { chromium } = require('playwright');
const fs = require('fs');
const BASE='http://localhost:5173';
const roles = {
  visitor:{routes:['/','/search','/search?q=baner','/compare','/about','/driving-rules','/contact','/privacy','/terms','/privacy/data-request','/auth/login','/auth/register','/auth/forgot-password','/for-schools','/for-trainers','/locality/baner','/school/skyline-driving-academy','/no-such-page']},
  admin:{email:'admin@driveiq.in',routes:['/admin','/admin/schools','/admin/reviews','/admin/localities','/admin/users','/admin/analytics','/admin/data-requests','/admin/messages','/admin/billing','/admin/prospects','/admin/outreach','/admin/audit']},
  school:{email:'info@skylinedrive.in',routes:['/dashboard','/dashboard/leads','/dashboard/learners','/dashboard/instructors','/dashboard/schedules','/dashboard/vehicles','/dashboard/payments','/dashboard/messages','/dashboard/team','/dashboard/profile','/dashboard/packages','/dashboard/reviews','/dashboard/analytics','/dashboard/settings','/dashboard/audit','/dashboard/billing']},
  instructor:{email:'trainer.skyline@driveiq.in',routes:['/instructor','/instructor/sessions','/instructor/messages','/instructor/learners','/instructor/leave','/instructor/attendance']},
  learner:{email:'learner.asha@driveiq.in',routes:['/learner','/learner/progress','/learner/sessions','/learner/documents','/learner/messages']},
};
const prefix={admin:'/admin',school:'/dashboard',instructor:'/instructor',learner:'/learner'};
const out={pages:[],guards:[]};
(async()=>{
 const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium/chrome-linux/chrome'}).catch(()=>chromium.launch());
 async function mk(email,vp){
  const ctx=await b.newContext({viewport:vp||{width:1366,height:850}});
  const p=await ctx.newPage();
  if(email){await p.goto(BASE+'/auth/login');await p.fill('[data-testid=input-login-email]',email);await p.fill('[data-testid=input-login-password]','password123');await p.click('[data-testid=button-login-submit]');await p.waitForURL(u=>!u.pathname.includes('/auth/login'),{timeout:15000}).catch(()=>{});await p.waitForTimeout(800);await p.getByRole('button',{name:'Accept',exact:true}).click({timeout:1500}).catch(()=>{});const cb=p.getByRole('checkbox').first();if(await p.getByText('Continue to portal').isVisible().catch(()=>false)){await cb.click().catch(()=>{});await p.getByText('Continue to portal').click().catch(()=>{});await p.waitForTimeout(500);}}
  return {ctx,p};
 }
 for(const [role,cfg] of Object.entries(roles)){
  for(const vpName of ['desktop','mobile']){
   const vp=vpName==='mobile'?{width:390,height:844}:undefined;
   const {ctx,p}=await mk(cfg.email,vp);
   if(cfg.email) out.pages.push({role,vpName,route:'(login landing)',url:p.url()});
   for(const r of cfg.routes){
    const rec={role,vpName,route:r,errors:[],api:[],page:[]};
    const onc=m=>{if(m.type()==='error')rec.errors.push(m.text().slice(0,200))};
    const onr=resp=>{if(resp.url().includes(':8000')&&resp.status()>=400)rec.api.push(resp.status()+' '+resp.request().method()+' '+resp.url().replace('http://localhost:8000',''))};
    const onpe=e=>rec.page.push(String(e).slice(0,200));
    p.on('console',onc);p.on('response',onr);p.on('pageerror',onpe);
    const t=Date.now();
    await p.goto(BASE+r,{waitUntil:'networkidle',timeout:30000}).catch(e=>rec.page.push('nav:'+e.message.slice(0,100)));
    await p.waitForTimeout(500);
    rec.ms=Date.now()-t;rec.finalUrl=p.url().replace(BASE,'');
    rec.overflow=await p.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+2);
    rec.textLen=await p.evaluate(()=>document.body.innerText.length);
    rec.h1=await p.evaluate(()=>document.querySelector('h1')?.innerText?.slice(0,60)||'');
    rec.bodyHint=await p.evaluate(()=>/something went wrong|error|failed to load|undefined|NaN/i.test(document.body.innerText)? (document.body.innerText.match(/.{0,40}(something went wrong|error|failed to load|undefined|NaN).{0,40}/i)||[''])[0]:'');
    if(vpName==='desktop'||rec.overflow) await p.screenshot({path:`shots2/${role}-${vpName}-${r.replace(/[^a-z0-9]/gi,'_')}.png`}).catch(()=>{});
    p.off('console',onc);p.off('response',onr);p.off('pageerror',onpe);
    out.pages.push(rec);
   }
   await ctx.close();
  }
 }
 // guards: each role against other roles' prefixes
 for(const [role,cfg] of Object.entries(roles)){
  if(role==='visitor')continue;
  const {ctx,p}=await mk(cfg.email);
  for(const [orole,pre] of Object.entries(prefix)){
   if(orole===role)continue;
   await p.goto(BASE+pre,{waitUntil:'networkidle'}).catch(()=>{});await p.waitForTimeout(400);
   out.guards.push({role,tried:pre,finalUrl:p.url().replace(BASE,''),h1:await p.evaluate(()=>document.querySelector('h1')?.innerText||'').catch(()=>'')});
  }
  await ctx.close();
 }
 // visitor guards
 {const {ctx,p}=await mk(null);for(const pre of Object.values(prefix)){await p.goto(BASE+pre,{waitUntil:'networkidle'}).catch(()=>{});await p.waitForTimeout(400);out.guards.push({role:'visitor',tried:pre,finalUrl:p.url().replace(BASE,'')});}await ctx.close();}
 fs.writeFileSync('sweep2.json',JSON.stringify(out,null,1));
 await b.close();
 console.log('done',out.pages.length);
})();
