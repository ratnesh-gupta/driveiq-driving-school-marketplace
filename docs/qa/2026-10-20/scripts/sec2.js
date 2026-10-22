const fs=require('fs');const API='http://localhost:8000/api';const P=JSON.parse(fs.readFileSync('tokens.json'));
async function call(m,path,tok,body,extra={}){const h={Accept:'application/json',...extra};if(!(body instanceof FormData))h['Content-Type']='application/json';if(tok)h.Authorization='Bearer '+tok;const r=await fetch(API+path,{method:m,headers:h,body:body?(body instanceof FormData?body:JSON.stringify(body)):undefined,redirect:'manual'});const t=await r.text();let j;try{j=JSON.parse(t)}catch{};return{s:r.status,t,j,h:r.headers}}
const R=[];const rep=(id,ok,note)=>{R.push({id,ok,note});console.log((ok?'PASS ':'FAIL ')+id+' — '+note)};
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 console.log('waiting 65s for throttles to reset…');await sleep(65000);
 // --- auth set (≤10 calls/min) ---
 const f1=await call('POST','/auth/forgot-password',null,{email:'admin@driveiq.in'});const f2=await call('POST','/auth/forgot-password',null,{email:'nobody'+Date.now()+'@example.com'});
 rep('A-S15 forgot-password does not enumerate users',f1.s===f2.s&&f1.t===f2.t,`known=${f1.s} ${f1.t.slice(0,60)} | unknown=${f2.s} ${f2.t.slice(0,60)}`);
 const l1=await call('POST','/auth/login',null,{email:'admin@driveiq.in',password:'x'});const l2=await call('POST','/auth/login',null,{email:'nouser@example.com',password:'x'});
 rep('A-S15b login error identical for known/unknown',l1.s===l2.s&&l1.t===l2.t,`${l1.s}:${l1.t.slice(0,70)} | ${l2.s}:${l2.t.slice(0,70)}`);
 const ra=await call('POST','/auth/register',null,{name:'Evil',email:`evil${Date.now()}@example.com`,password:'Passw0rd!QA',role:'admin'});rep('A-S16 cannot self-register as admin',ra.s===422,`status=${ra.s}`);
 const wp=await call('POST','/auth/register',null,{name:'Weak',email:`weak${Date.now()}@example.com`,password:'password',role:'learner'});rep('A-S17 weak password rejected server-side',wp.s===422,`status=${wp.s}`);
 const L=await call('POST','/auth/login',null,{email:'rahul@gmail.com',password:'password123'});
 let codes=[];for(let i=0;i<8;i++)codes.push((await call('POST','/auth/login',null,{email:'admin@driveiq.in',password:'bad'+i})).s);rep('A-S14 brute force locked out after ≤5 attempts per account',codes.includes(429),codes.join(','));
 // --- scope: second learner / instructor ---
 const lb=await call('POST','/schools/1/learners',P.owner1,{name:'Learner B QA',mobile:'9000000002'});rep('R1 owner creates learner B',lb.s===201,`status=${lb.s} id=${lb.j?.id}`);const B=lb.j?.id;
 const ib=await call('POST','/schools/1/instructors',P.owner1,{name:'Trainer B QA',phone:'9000000003',licenseNumber:'MH-QA-1',experienceYears:3});rep('R2 owner creates instructor B',ib.s===201,`status=${ib.s} ${ib.t.slice(0,150)}`);const IB=ib.j?.id;
 const sc=[];
 for(const [who,p] of [['learner',`/learners/${B}`],['learner',`/learners/${B}/progress`],['learner',`/learners/${B}/sessions`],['learner',`/learners/${B}/documents`],['instructor',`/learners/${B}`],['instructor',`/learners/${B}/progress`],['instructor',`/learners/${B}/sessions`],['instructor',`/learners/${B}/documents`],['instructor',`/instructors/${IB}`],['instructor',`/instructors/${IB}/documents`]]){const r=await call('GET',p,P[who]);sc.push([who,p,r.s]);}
 for(const [w,p,s] of sc)rep('R3 '+w+' GET '+p+' blocked (not own/assigned)',s===403||s===404,`status=${s}`);
 // instructor can list all school learners/schedules?
 const il=await call('GET','/schools/1/learners',P.instructor);rep('R4 trainer cannot list ALL school learners',il.s===403||(il.j&&Array.isArray(il.j)&&il.j.every(x=>x.assignedInstructorId===1)),`status=${il.s} count=${Array.isArray(il.j)?il.j.length:'-'}`);
 const ip=await call('GET','/schools/1/payments',P.instructor);rep('R5 trainer cannot see payments',ip.s===403,`status=${ip.s}`);
 const lp=await call('GET','/schools/1/payments',P.learner);rep('R6 learner cannot see payments',lp.s===403,`status=${lp.s}`);
 const lpp=await call('PATCH','/learners/1',P.learner,{status:'completed',assignedInstructorId:2,packageId:1});rep('R7 learner cannot edit own status/assignment',lpp.s===403,`status=${lpp.s} ${lpp.t.slice(0,80)}`);
 const prog=await call('PUT','/learners/1/progress',P.learner,{skills:[{skillName:'highway_driving',percentage:100}]});rep('R8 learner cannot set own progress',prog.s===403||prog.s===422,`status=${prog.s}`);
 // --- uploads ---
 const up=async(who,id,name,type,content,field='file')=>{const fd=new FormData();fd.append('type','other');fd.append(field,new Blob([content],{type}),name);return call('POST',`/learners/${id}/documents`,P[who],fd)};
 const u1=await up('learner',1,'ok.pdf','application/pdf','%PDF-1.4 test');rep('U1 valid PDF accepted',u1.s===201||u1.s===200,`status=${u1.s}`);
 const u2=await up('learner',1,'shell.php','application/x-php','<?php system($_GET["c"]);');rep('U2 .php rejected',u2.s===422,`status=${u2.s}`);
 const u3=await up('learner',1,'shell.php.jpg','image/jpeg','<?php system($_GET["c"]);');rep('U3 php payload with .jpg ext rejected (content sniff)',u3.s===422,`status=${u3.s} ${u3.t.slice(0,90)}`);
 const u4=await up('learner',1,'evil.svg','image/svg+xml','<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');rep('U4 svg rejected',u4.s===422,`status=${u4.s}`);
 const u5=await up('learner',1,'big.pdf','application/pdf','%PDF'+'0'.repeat(6*1024*1024));rep('U5 >5MB rejected',u5.s===422||u5.s===413,`status=${u5.s}`);
 const u6=await up('learner',1,'../../../etc/passwd.pdf','application/pdf','%PDF-1.4');rep('U6 path-traversal filename handled',u6.s<500,`status=${u6.s} ${u6.j?.fileName||''}`);
 const u7=await up('learner',B,'x.pdf','application/pdf','%PDF-1.4');rep('U7 learner cannot upload to another learner',u7.s===403||u7.s===404,`status=${u7.s}`);
 const u8=await up('owner2',1,'x.pdf','application/pdf','%PDF-1.4');rep('U8 other school cannot upload to school1 learner',u8.s===403||u8.s===404,`status=${u8.s}`);
 // doc link / file access
 const docs=await call('GET','/learners/1/documents',P.learner);const d=(docs.j||[]).find(x=>x.fileName);console.log('docs',docs.s,JSON.stringify(docs.j).slice(0,160));
 if(d){const lk=await call('GET',`/documents/learner/${d.id}/link`,P.learner);rep('U9 owner of doc gets signed link',lk.s===200&&!!(lk.j?.url),`status=${lk.s}`);
  const lk2=await call('GET',`/documents/learner/${d.id}/link`,P.owner2);rep('U10 other school denied doc link',lk2.s===403||lk2.s===404,`status=${lk2.s}`);
  const lk3=await call('GET',`/documents/learner/${d.id}/link`,P.learnerNew);rep('U10b unrelated learner denied doc link',lk3.s===403||lk3.s===404,`status=${lk3.s}`);
  const lk4=await call('GET',`/documents/learner/${d.id}/link`,null);rep('U10c anon denied doc link',lk4.s===401,`status=${lk4.s}`);
  if(lk.j?.url){const raw=await fetch(lk.j.url);rep('U11 signed link downloads file w/o auth',raw.status===200,`status=${raw.status} ct=${raw.headers.get('content-type')} cd=${raw.headers.get('content-disposition')} xcto=${raw.headers.get('x-content-type-options')}`);
   const tam=await fetch(lk.j.url.replace(/signature=[0-9a-f]+/,'signature=deadbeef'));rep('U12 tampered signature rejected',tam.status===403,`status=${tam.status}`);
   const tam2=await fetch(lk.j.url.replace(/\/documents\/learner\/\d+\//,'/documents/learner/'+(d.id+1)+'/'));rep('U12b id swap with same signature rejected',tam2.status===403||tam2.status===404,`status=${tam2.status}`);}}
 fs.writeFileSync('sec2.json',JSON.stringify(R,null,1));
})();
