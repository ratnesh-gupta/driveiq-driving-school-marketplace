const { chromium } = require('playwright');const BASE='http://localhost:5173';
const step=async(n,f)=>{try{const r=await f();console.log('PASS',n,r?'— '+r:'')}catch(e){console.log('FAIL',n,'—',String(e.message).split('\n')[0].slice(0,200))}};
(async()=>{const b=await chromium.launch();
 for(const mode of ['granted','denied']){
  const ctx=await b.newContext({viewport:{width:1366,height:850},...(mode==='granted'?{geolocation:{latitude:18.5590,longitude:73.7868},permissions:['geolocation']}:{})});const p=await ctx.newPage();
  if(mode==='denied')await p.addInitScript(()=>{navigator.geolocation.getCurrentPosition=(s,e)=>e({code:1,message:'User denied Geolocation'});navigator.geolocation.watchPosition=(s,e)=>e({code:1,message:'denied'})});
  const reqs=[];p.on('request',r=>{if(r.url().includes('/api/schools?'))reqs.push(r.url().replace('http://localhost:8000',''))});
  await p.goto(BASE+'/search',{waitUntil:'networkidle'});await p.getByRole('button',{name:'Accept',exact:true}).click({timeout:800}).catch(()=>{});
  await step(`G-${mode} click "Use my location"`,async()=>{await p.getByRole('button',{name:'Use my location'}).click();await p.waitForTimeout(2500);const t=await p.innerText('body');const m=t.match(/.{0,60}(denied|unable|permission|could not|blocked|location).{0,80}/gi)||[];return `geoReq=${reqs.filter(r=>/lat=/.test(r)).slice(-1)[0]||'none'} | msgs=${JSON.stringify(m.slice(-2)).replace(/\\n/g,' ')} | cards=${await p.locator('[data-testid^=card-school]').count()}`});
  await p.screenshot({path:`shots2/geo-${mode}.png`});await ctx.close()}
 // mobile menu
 const ctx=await b.newContext({viewport:{width:390,height:844}});const p=await ctx.newPage();
 await p.goto(BASE+'/auth/login');await p.fill('[data-testid=input-login-email]','info@skylinedrive.in');await p.fill('[data-testid=input-login-password]','password123');await p.click('[data-testid=button-login-submit]');await p.waitForTimeout(2000);
 await p.getByRole('button',{name:'Accept',exact:true}).click({timeout:800}).catch(()=>{});
 if(await p.getByText('Continue to portal').isVisible().catch(()=>false)){await p.getByRole('checkbox').first().click();await p.getByText('Continue to portal').click();await p.waitForTimeout(800)}
 await step('MB1b mobile hamburger opens nav; has accessible name',async()=>{const hb=p.locator('header button').first();const nm=await hb.getAttribute('aria-label');await hb.click();await p.waitForTimeout(600);await p.screenshot({path:'shots2/mobile-nav-open.png'});return `aria-label=${nm} linksVisible=${await p.locator('a[href="/dashboard/learners"]').first().isVisible().catch(()=>false)}`});
 await ctx.close();
 // i18n
 const c2=await b.newContext({viewport:{width:1366,height:850}});const q=await c2.newPage();await q.goto(BASE+'/',{waitUntil:'networkidle'});await q.getByRole('button',{name:'Accept',exact:true}).click({timeout:800}).catch(()=>{});
 await step('I1 language menu',async()=>{await q.locator('header').getByText('English').first().click();await q.waitForTimeout(500);await q.screenshot({path:'shots2/i18n-menu.png'});return (await q.locator('[role=menu],[role=listbox],[role=dialog]').allInnerTexts()).join('|').slice(0,100)});
 await b.close()})();
