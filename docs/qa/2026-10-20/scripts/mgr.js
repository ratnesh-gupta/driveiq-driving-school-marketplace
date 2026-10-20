const fs=require('fs');const API='http://localhost:8001/api';const P=JSON.parse(fs.readFileSync('tokens.json'));
async function call(m,p,tok,b){const h={Accept:'application/json','Content-Type':'application/json'};if(tok)h.Authorization='Bearer '+tok;const r=await fetch(API+p,{method:m,headers:h,body:b?JSON.stringify(b):undefined});const t=await r.text();let j;try{j=JSON.parse(t)}catch{};return{s:r.status,t,j}}
const R=[];const rep=(id,ok,note)=>{R.push({id,ok,note});console.log((ok?'PASS ':'FAIL ')+id+' — '+note)};
(async()=>{const u=Date.now().toString().slice(-6);const em=`mgr_${u}@example.com`;
 const inv=await call('POST','/schools/1/team',P.owner1,{email:em,role:'manager'});rep('M1 owner invites manager',inv.s===201||inv.s===200,`status=${inv.s} ${inv.t.slice(0,120)}`);
 await new Promise(r=>setTimeout(r,1500));const log=fs.readFileSync('/home/user/driveiq-driving-school-marketplace/backend/storage/logs/laravel.log','utf8');
 const tok=[...log.matchAll(/team\/accept\?token=([A-Za-z0-9]+)/g)].map(x=>x[1]).pop();console.log('invite token found',!!tok);
 const pv=await call('GET','/team/invitations/'+tok);console.log('preview',pv.s,pv.t.slice(0,120));
 const ac=await call('POST','/team/accept',null,{token:tok,name:'QA Manager',password:'Passw0rd!QA',password_confirmation:'Passw0rd!QA'});rep('M2 manager accepts invite',ac.s===200||ac.s===201,`status=${ac.s} ${ac.t.slice(0,140)}`);
 const re=await call('POST','/team/accept',null,{token:tok,name:'QA Manager',password:'Passw0rd!QA',password_confirmation:'Passw0rd!QA'});rep('M3 invite token single-use',re.s===404||re.s===422,`status=${re.s}`);
 const mt=(await call('POST','/auth/login',null,{email:em,password:'Passw0rd!QA'})).j?.token;rep('M4 manager can log in',!!mt,'token='+!!mt);
 const T=mt;const t=[
  ['GET','/schools/1/learners',200],['GET','/inquiries',200],['GET','/schools/1/schedules',200],['GET','/schools/1/payments',200],['POST','/schools/1/vehicles',201,{registrationNumber:'MH12MGR'+u.slice(0,3),type:'car'}],
  ['GET','/schools/1/team',[200,403]],['POST','/schools/1/team',403,{email:'x'+u+'@example.com',role:'manager'}],['DELETE','/schools/1/team/1',403],
  ['GET','/schools/1/subscription',[200,403]],['GET','/schools/1/billing/invoices',[200,403]],['POST','/schools/1/billing/invoices',403,{}],['PUT','/schools/1/settings',[403,422]],['PATCH','/schools/1',[403,422],{name:'Mgr Rename'}],['GET','/schools/1/audit-logs',[200,403]],['GET','/schools/1/google-business',[200,403]],['DELETE','/schools/1/google-business',403],
  ['GET','/admin/users',403],['GET','/schools/7/learners',[403,404]],
 ];
 for(const [m,p,exp,b] of t){const r=await call(m,p,T,b);const ok=Array.isArray(exp)?exp.includes(r.s):r.s===exp;rep(`M5 manager ${m} ${p}`,ok,`status=${r.s} expected ${JSON.stringify(exp)} ${ok?'':r.t.slice(0,80)}`)}
 // manager can see team list? owner-only per code; record
 // removal revokes access
 const tm=await call('GET','/schools/1/team',P.owner1);const mem=(tm.j||[]).find?.(x=>x.email===em||x.inviteEmail===em);console.log('team list',tm.s,JSON.stringify(tm.j).slice(0,200));
 if(mem){const rm=await call('DELETE',`/schools/1/team/${mem.id}`,P.owner1);const after=await call('GET','/schools/1/learners',T);rep('M6 removed manager loses access immediately',rm.s<300&&(after.s===401||after.s===403),`remove=${rm.s} afterAccess=${after.s}`);}
 fs.writeFileSync('mgr.json',JSON.stringify(R,null,1));
})();
