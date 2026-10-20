const { chromium } = require('playwright');
const BASE='http://localhost:5173', API='http://localhost:8000/api';
const R=[];const log=(id,ok,note)=>{R.push({id,ok,note});console.log((ok?'PASS ':'FAIL ')+id+' — '+note)};
const uniq=Date.now().toString().slice(-6);
(async()=>{
 const b=await chromium.launch();
 const ctx=await b.newContext({viewport:{width:1366,height:850}});
 const p=await ctx.newPage();
 const errs=[];p.on('pageerror',e=>errs.push(String(e)));
 const apiFails=[];p.on('response',r=>{if(r.url().includes(':8000')&&r.status()>=400)apiFails.push(r.status()+' '+r.request().method()+' '+r.url().replace('http://localhost:8000',''))});
 const cookie=async()=>p.getByRole('button',{name:'Accept',exact:true}).click({timeout:1200}).catch(()=>{});
 // V1 search & filter
 await p.goto(BASE+'/search',{waitUntil:'networkidle'});await cookie();
 const n0=await p.locator('[data-testid^=card-school]').count();
 log('V1 search lists schools',n0>0,'cards='+n0);
 // V2 school detail + inquiry
 await p.goto(BASE+'/school/skyline-driving-academy',{waitUntil:'networkidle'});
 const wa=await p.locator('[data-testid=link-whatsapp]').first().getAttribute('href').catch(()=>null);
 log('V2a WhatsApp deeplink present',!!wa&&wa.includes('wa.me'),String(wa).slice(0,90));
 await p.click('[data-testid=button-send-inquiry]').catch(()=>{});
 await p.fill('[data-testid=input-inquiry-name]','QA Tester '+uniq);
 await p.fill('[data-testid=input-inquiry-phone]','98765'+uniq.slice(0,5));
 await p.fill('[data-testid=input-inquiry-email]','qa'+uniq+'@example.com').catch(()=>{});
 await p.fill('[data-testid=textarea-inquiry-message]','QA flow test inquiry').catch(()=>{});
 await p.click('[data-testid=button-submit-inquiry]');
 await p.waitForTimeout(1500);
 const body=await p.innerText('body');
 log('V2b inquiry submit shows confirmation',/thank|submitted|sent|received/i.test(body),(body.match(/.{0,50}(thank|submitted|sent|received).{0,50}/i)||['no confirmation text'])[0].replace(/\n/g,' '));
 await p.screenshot({path:'shots2/flow-inquiry.png'});
 // V3 invalid inquiry (empty phone)
 await p.reload({waitUntil:'networkidle'});await p.click('[data-testid=button-send-inquiry]').catch(()=>{});
 await p.fill('[data-testid=input-inquiry-name]','X');await p.fill('[data-testid=input-inquiry-phone]','123');
 await p.click('[data-testid=button-submit-inquiry]');await p.waitForTimeout(800);
 const b2=await p.innerText('body');
 log('V3 invalid phone rejected',/invalid|valid|10|required|must/i.test(b2)||apiFails.some(f=>f.startsWith('422')),'api fails: '+apiFails.slice(-2).join(';'));
 // V4 compare
 await p.goto(BASE+'/search',{waitUntil:'networkidle'});
 const adds=p.getByRole('button',{name:/Add to Compare/i});const ac=await adds.count();
 if(ac>=2){await adds.nth(0).click();await adds.nth(1).click();await p.waitForTimeout(500);}
 const cmpLink=p.getByRole('link',{name:/compare/i}).or(p.getByRole('button',{name:/^compare/i}));
 log('V4 compare buttons',ac>=2,'add buttons='+ac);
 await p.goto(BASE+'/compare?ids=1,2',{waitUntil:'networkidle'});
 log('V4b compare page loads',(await p.innerText('body')).length>500,'len');
 // V5 contact form
 await p.goto(BASE+'/contact',{waitUntil:'networkidle'});
 await p.fill('[data-testid=input-contact-name]','QA');await p.fill('[data-testid=input-contact-email]','qa@example.com');await p.fill('[data-testid=input-contact-subject]','Test subject');await p.fill('[data-testid=textarea-contact-message]','Hello from QA flow test, please ignore.');
 await p.click('[data-testid=button-contact-submit]');await p.waitForTimeout(1500);
 {const t=await p.innerText('body');log('V5 contact form',/thank|sent|received|success/i.test(t),t.slice(0,300).replace(/\n/g,' | '));await p.screenshot({path:'shots2/flow-contact.png'});}
 // A1 register learner
 await p.goto(BASE+'/auth/register',{waitUntil:'networkidle'});
 await p.click('[data-testid=button-account-type-learner]').catch(()=>{});
 await p.fill('[data-testid=input-register-name]','QA Learner '+uniq);await p.fill('[data-testid=input-register-email]',`qalearner${uniq}@example.com`);await p.fill('[data-testid=input-register-password]','Passw0rd!QA');
 await p.fill('[data-testid=input-register-phone]','9123456'+uniq.slice(0,3)).catch(()=>{});
 for(const t of ['agree-terms','agree-privacy','agree-processing'])await p.click(`[data-testid=checkbox-${t}]`).catch(()=>{});
 await p.click('[data-testid=button-register-submit]');await p.waitForTimeout(2500);
 log('A1 learner registration → portal',p.url().includes('/learner'),p.url());
 await p.screenshot({path:'shots2/flow-register-learner.png'});
 // A2 wrong password
 const c2=await b.newContext();const p2=await c2.newPage();
 await p2.goto(BASE+'/auth/login');await p2.fill('[data-testid=input-login-email]','admin@driveiq.in');await p2.fill('[data-testid=input-login-password]','wrong');await p2.click('[data-testid=button-login-submit]');await p2.waitForTimeout(1500);
 const lb=await p2.innerText('body');
 log('A2 wrong password message',/invalid|incorrect|credentials|failed/i.test(lb)&&p2.url().includes('login'),(lb.match(/.{0,30}(invalid|incorrect|credentials).{0,40}/i)||['none'])[0]);
 // A3 weak password at register
 const c3=await b.newContext();const p3=await c3.newPage();
 await p3.goto(BASE+'/auth/register');await p3.click('[data-testid=button-account-type-learner]').catch(()=>{});
 await p3.fill('[data-testid=input-register-name]','Weak');await p3.fill('[data-testid=input-register-email]',`weak${uniq}@example.com`);await p3.fill('[data-testid=input-register-password]','123');
 for(const t of ['agree-terms','agree-privacy','agree-processing'])await p3.click(`[data-testid=checkbox-${t}]`).catch(()=>{});
 const dis=await p3.locator('[data-testid=button-register-submit]').isDisabled();
 log('A3 weak password blocked (submit disabled)',dis,'disabled='+dis);
 console.log('pageerrors:',errs.length,errs.slice(0,3));console.log('apiFails:',apiFails.join('\n'));
 await b.close();
 require('fs').writeFileSync('flow1.json',JSON.stringify({R,errs,apiFails},null,1));
})();
