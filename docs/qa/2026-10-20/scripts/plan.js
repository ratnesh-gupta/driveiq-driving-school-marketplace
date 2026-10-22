const fs=require('fs');const API='http://localhost:8001/api';const P=JSON.parse(fs.readFileSync('tokens.json'));
async function call(m,p,tok,b){const h={Accept:'application/json','Content-Type':'application/json'};if(tok)h.Authorization='Bearer '+tok;const r=await fetch(API+p,{method:m,headers:h,body:b?JSON.stringify(b):undefined});const t=await r.text();return{s:r.status,t}}
(async()=>{console.log('--- trial expired, basic plan ---');
 for(const [m,p,b] of [['GET','/schools/1/entitlements'],['GET','/schools/1/learners'],['POST','/schools/1/learners',{name:'Gate Test',mobile:'9222222222'}],['GET','/schools/1/payments'],['POST','/schools/1/payments/package',{learnerId:1,packageId:1}],['GET','/schools/1/vehicles'],['POST','/schools/1/vehicles',{registrationNumber:'MH12GATE1',type:'car'}],['POST','/schools/1/schedules',{instructorId:1,sessionDate:'2026-12-01',startTime:'10:00',endTime:'11:00'}],['GET','/schools/1/analytics'],['GET','/inquiries'],['PATCH','/inquiries/1',{status:'contacted'}],['POST','/schools/1/instructors',{name:'Gate T',phone:'9333333333'}]]){
  const r=await call(m,p,P.owner1,b);console.log(m.padEnd(6),p.padEnd(32),r.s,r.s>=400?r.t.slice(0,110):'')}
 console.log('trainer portal while locked:',(await call('GET','/instructor/sessions',P.instructor)).s,'| learner portal:',(await call('GET','/learner/me',P.learner)).s);
})();
