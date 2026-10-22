const API='http://localhost:8000/api';
async function call(m,p,tok,b){const h={Accept:'application/json','Content-Type':'application/json'};if(tok)h.Authorization='Bearer '+tok;const r=await fetch(API+p,{method:m,headers:h,body:b?JSON.stringify(b):undefined,redirect:'manual'});const t=await r.text();let j;try{j=JSON.parse(t)}catch{};return{s:r.status,t,j,loc:r.headers.get('location')}}
(async()=>{const u=Date.now().toString().slice(-6);const em=`owner3_${u}@example.com`;
 const reg=await call('POST','/auth/register',null,{name:'QA Third Driving '+u,email:em,password:'Passw0rd!QA',role:'school',listingType:'school'});
 const tok=(await call('POST','/auth/login',null,{email:em,password:'Passw0rd!QA'})).j.token;
 const me=await call('GET','/auth/me',tok);const sid=me.j.schoolId;console.log('school',sid);
 const st=async()=>{const r=await call('GET','/schools/'+sid,tok);return r.j?.listingStatus||r.t.slice(0,60)};
 console.log('listing status before verify:',await st());
 const pubSearch=async()=>JSON.stringify((await call('GET','/schools?q=QA%20Third')).j).includes('QA Third');
 console.log('in public search before:',await pubSearch());
 const n=await call('POST','/auth/email/verification-notification',tok,{});console.log('notify',n.s,n.t.slice(0,100));
 await new Promise(r=>setTimeout(r,1500));
 const fs=require('fs');const log=fs.readFileSync('/home/user/driveiq-driving-school-marketplace/backend/storage/logs/laravel.log','utf8');
 const m=[...log.matchAll(/(http:\/\/localhost:8000\/api\/auth\/email\/verify\/[^\s"'<>)\]]+)/g)].map(x=>x[1].replace(/&amp;/g,'&')).pop();console.log('verify link found:',!!m,m?.slice(0,90));
 if(m){const t=await fetch(m.replace(/signature=[0-9a-f]+/,'signature=bad'),{redirect:'manual'});console.log('tampered link ->',t.status);
  const ok=await fetch(m,{redirect:'manual'});console.log('real link ->',ok.status,ok.headers.get('location'));
  console.log('listing status after verify:',await st(),' public search:',await pubSearch());
  const again=await fetch(m,{redirect:'manual'});console.log('replay ->',again.status);}
 // reset password flow
 const f=await call('POST','/auth/forgot-password',null,{email:em});console.log('forgot',f.s);await new Promise(r=>setTimeout(r,1000));
 const log2=fs.readFileSync('/home/user/driveiq-driving-school-marketplace/backend/storage/logs/laravel.log','utf8');
 const rt=[...log2.matchAll(/reset-password\?[^\s"'<>]*token=([A-Za-z0-9]+)/g)].map(x=>x[1]).pop();console.log('reset token found',!!rt);
 if(rt){const w=await call('POST','/auth/reset-password',null,{email:em,token:rt,password:'weak',password_confirmation:'weak'});console.log('weak reset',w.s);
  const r1=await call('POST','/auth/reset-password',null,{email:em,token:rt,password:'NewPassw0rd!QA',password_confirmation:'NewPassw0rd!QA'});console.log('reset',r1.s,r1.t.slice(0,100));
  const r2=await call('POST','/auth/reset-password',null,{email:em,token:rt,password:'Another1Passw0rd!',password_confirmation:'Another1Passw0rd!'});console.log('reset token replay',r2.s);
  const oldTok=await call('GET','/auth/me',tok);console.log('old session token after pw reset ->',oldTok.s,'(expect 401)');
  const l=await call('POST','/auth/login',null,{email:em,password:'NewPassw0rd!QA'});console.log('login new pw',l.s);}
})();
