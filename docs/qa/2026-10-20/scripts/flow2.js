const { chromium } = require('playwright');
const BASE='http://localhost:5173';const uniq=Date.now().toString().slice(-6);
(async()=>{
 const b=await chromium.launch();const ctx=await b.newContext({viewport:{width:1366,height:850}});const p=await ctx.newPage();
 const fails=[];p.on('response',async r=>{if(r.url().includes(':8000')&&r.status()>=400)fails.push(r.status()+' '+r.request().method()+' '+r.url().replace('http://localhost:8000','')+' '+(await r.text().catch(()=>'')).slice(0,150))});
 await p.goto(BASE+'/school/skyline-driving-academy',{waitUntil:'networkidle'});await p.getByRole('button',{name:'Accept',exact:true}).click({timeout:1200}).catch(()=>{});
 await p.click('[data-testid=button-send-inquiry]');await p.waitForTimeout(3500);
 await p.fill('[data-testid=input-inquiry-name]','QA Tester '+uniq);await p.fill('[data-testid=input-inquiry-phone]','98765'+uniq.slice(0,5));await p.fill('[data-testid=textarea-inquiry-message]','QA inquiry');
 await p.click('[data-testid=button-submit-inquiry]');await p.waitForTimeout(1500);
 console.log('inquiry body:',(await p.innerText('body')).match(/.{0,60}(thank|submitted|success|received|sent).{0,60}/i)?.[0]?.replace(/\n/g,' ')||'NO CONFIRMATION');
 await p.goto(BASE+'/contact',{waitUntil:'networkidle'});
 await p.fill('[data-testid=input-contact-name]','QA');await p.fill('[data-testid=input-contact-email]','qa@example.com');await p.fill('[data-testid=input-contact-subject]','Test subject');await p.fill('[data-testid=textarea-contact-message]','Hello from QA flow test, please ignore.');
 await p.waitForTimeout(3500);await p.click('[data-testid=button-contact-submit]');await p.waitForTimeout(1500);
 console.log('contact:',(await p.innerText('body')).match(/.{0,60}(thank|sent|success|received).{0,60}/i)?.[0]?.replace(/\n/g,' ')||'NO CONFIRMATION');
 // new learner portal
 const c2=await b.newContext({viewport:{width:1366,height:850}});const q=await c2.newPage();
 await q.goto(BASE+'/auth/register');await q.getByRole('button',{name:'Accept',exact:true}).click({timeout:2000}).catch(()=>{});await q.click('[data-testid=button-account-type-learner]');
 await q.fill('[data-testid=input-register-name]','QA Learner '+uniq);await q.fill('[data-testid=input-register-email]',`qal2${uniq}@example.com`);await q.fill('[data-testid=input-register-password]','Passw0rd!QA');
 for(const t of ['agree-terms','agree-privacy','agree-processing'])await q.click(`[data-testid=checkbox-${t}]`);
 await q.click('[data-testid=button-register-submit]');await q.waitForTimeout(2500);
 await q.getByRole('button',{name:'Accept',exact:true}).click({timeout:1200}).catch(()=>{});
 if(await q.getByText('Continue to portal').isVisible().catch(()=>false)){await q.getByRole('checkbox').first().click();await q.getByText('Continue to portal').click();await q.waitForTimeout(600)}
 for(const r of ['/learner','/learner/progress','/learner/sessions','/learner/documents','/learner/messages']){await q.goto(BASE+r,{waitUntil:'networkidle'});await q.waitForTimeout(400);await q.screenshot({path:`shots2/newlearner-${r.replace(/\//g,'_')}.png`});}
 console.log(fails.join('\n'));await b.close();
})();
