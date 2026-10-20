const { chromium } = require('playwright');
const BASE='http://localhost:5173';
(async()=>{
 const b=await chromium.launch();const p=await (await b.newContext({viewport:{width:1366,height:850}})).newPage();
 const fails=[];p.on('response',async r=>{if(r.url().includes(':8000')&&r.status()>=400)fails.push(r.status()+' '+r.request().method()+' '+r.url().replace('http://localhost:8000','')+' '+(await r.text().catch(()=>'')).slice(0,160))});
 const errs=[];p.on('pageerror',e=>errs.push(String(e)));
 await p.goto(BASE+'/auth/login');await p.getByRole('button',{name:'Accept',exact:true}).click({timeout:2000}).catch(()=>{});
 await p.fill('[data-testid=input-login-email]','info@skylinedrive.in');await p.fill('[data-testid=input-login-password]','password123');await p.click('[data-testid=button-login-submit]');
 await p.waitForTimeout(1200);if(await p.getByText('Continue to portal').isVisible().catch(()=>false)){await p.getByRole('checkbox').first().click();await p.getByText('Continue to portal').click();await p.waitForTimeout(500)}
 await p.goto(BASE+'/dashboard/leads',{waitUntil:'networkidle'});
 const rows=await p.locator('[data-testid^=row-lead-]').count();console.log('lead rows',rows);
 await p.click('[data-testid=button-follow-up-due]').catch(e=>console.log('no followup btn'));await p.waitForTimeout(1200);
 console.log('after follow-up filter, rows',await p.locator('[data-testid^=row-lead-]').count());
 await p.click('[data-testid=button-follow-up-due]').catch(()=>{});await p.waitForTimeout(800);
 await p.click('[data-testid=select-sort]').catch(()=>{});await p.waitForTimeout(400);await p.screenshot({path:'shots2/flow3-sort.png'});
 await p.keyboard.press('Escape');
 const first=p.locator('[data-testid^=select-lead-status-]').first();
 await first.click();await p.waitForTimeout(400);await p.screenshot({path:'shots2/flow3-status.png'});
 const opts=await p.getByRole('option').allInnerTexts();console.log('status options',opts);
 await p.getByRole('option',{name:/contacted/i}).first().click().catch(()=>{});await p.waitForTimeout(1200);
 console.log('toast/body:',(await p.innerText('body')).match(/.{0,40}(updated|contacted|failed).{0,40}/i)?.[0]?.replace(/\n/g,' '));
 await p.locator('[data-testid^=button-lead-notes-]').first().click();await p.waitForTimeout(1000);await p.screenshot({path:'shots2/flow3-notes.png'});
 console.log('FAILS:\n'+fails.join('\n'));console.log('pageerr',errs);
 await b.close();
})();
