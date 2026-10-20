const fs=require('fs');const API='http://localhost:8001/api';const P=JSON.parse(fs.readFileSync('tokens.json'));
async function call(m,p,tok,b){const h={Accept:'application/json','Content-Type':'application/json'};if(tok)h.Authorization='Bearer '+tok;const r=await fetch(API+p,{method:m,headers:h,body:b?JSON.stringify(b):undefined});const t=await r.text();let j;try{j=JSON.parse(t)}catch{};return{s:r.status,t,j}}
const tally=a=>a.reduce((o,x)=>(o[x.s]=(o[x.s]||0)+1,o),{});
const R=[];const rep=(id,ok,note)=>{R.push({id,ok,note});console.log((ok?'PASS ':'FAIL ')+id+' — '+note)};
(async()=>{
 const N=12;
 // overlapping schedule race
 let r=await Promise.all(Array.from({length:N},()=>call('POST','/schools/1/schedules',P.owner1,{instructorId:1,sessionDate:'2026-11-12',startTime:'09:00',endTime:'10:00',learnerName:'race'})));
 console.log('schedule race',JSON.stringify(tally(r)));rep('C1 concurrent identical session creates → exactly 1 booked',r.filter(x=>x.s===201).length===1,`created=${r.filter(x=>x.s===201).length} of ${N}`);
 // vehicle duplicate race
 r=await Promise.all(Array.from({length:N},()=>call('POST','/schools/1/vehicles',P.owner1,{registrationNumber:'MH14RACE99',type:'car'})));
 console.log('vehicle race',JSON.stringify(tally(r)));rep('C2 concurrent duplicate vehicle → exactly 1',r.filter(x=>x.s===201).length===1,`created=${r.filter(x=>x.s===201).length}`);
 // payment mark-paid race
 const pu=await call('POST','/schools/1/payments/package',P.owner1,{learnerId:2,packageId:1,method:'cash',markPaid:false});const pid=pu.j?.payment?.id;
 r=await Promise.all(Array.from({length:N},()=>call('POST',`/payments/${pid}/mark-paid`,P.owner1,{})));
 console.log('pay race',JSON.stringify(tally(r)));
 const pays=await call('GET','/schools/1/payments',P.owner1);const mine=(pays.j||[]).filter(x=>x.id===pid);
 rep('C3 concurrent mark-paid → single paid transition',r.filter(x=>x.s===200).length<=1,`200s=${r.filter(x=>x.s===200).length}`);
 // duplicate purchase race (same learner, same package)
 r=await Promise.all(Array.from({length:6},()=>call('POST','/schools/1/payments/package',P.owner1,{learnerId:2,packageId:1,method:'cash',markPaid:false})));
 console.log('purchase race',JSON.stringify(tally(r)),'(info: duplicate pending invoices for same learner+package)');
 // learner duplicate (same mobile) race
 r=await Promise.all(Array.from({length:N},()=>call('POST','/schools/1/learners',P.owner1,{name:'Race Learner',mobile:'9111111111'})));
 console.log('learner race',JSON.stringify(tally(r)),'(info: no uniqueness on mobile)');
 // review duplicate race
 await call('DELETE','/reviews/0',P.admin);
 r=await Promise.all(Array.from({length:N},()=>call('POST','/reviews',P.learner,{schoolId:1,authorName:'Asha',rating:5,content:'race review'})));
 console.log('review race',JSON.stringify(tally(r)));rep('C4 concurrent reviews by same learner → at most 1',r.filter(x=>x.s===201).length<=1,`created=${r.filter(x=>x.s===201).length}`);
 // inquiry flood from one IP
 await new Promise(r=>setTimeout(r,60000));
 const fl=[];for(let i=0;i<30;i++)fl.push(call('POST','/inquiries',null,{schoolId:2,name:'Flood '+i,phone:'9876543210',vehicleType:'car',formStartedAt:Date.now()-9000}));
 r=await Promise.all(fl);console.log('inquiry flood',JSON.stringify(tally(r)));rep('C5 inquiry flood throttled to ~5/min/IP',r.filter(x=>x.s===201).length<=6&&r.some(x=>x.s===429),JSON.stringify(tally(r)));
 // same-phone duplicate inquiry spam from rotating nothing: duplicates to same school
 fs.writeFileSync('race.json',JSON.stringify(R,null,1));
})();
