const fs=require('fs');const API='http://localhost:8000/api';
const call=async(p,b)=>{const r=await fetch(API+p,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify(b)});return {s:r.status,j:await r.json().catch(()=>({}))}};
(async()=>{const u=Date.now().toString().slice(-6);
 const reg=await call('/auth/register',{name:'QA Second School '+u,email:`owner2_${u}@example.com`,password:'Passw0rd!QA',role:'school',listingType:'school'});
 const reg2=await call('/auth/register',{name:'QA Learner '+u,email:`qalearner_${u}@example.com`,password:'Passw0rd!QA',role:'learner'});
 const L=async(e,p)=>(await call('/auth/login',{email:e,password:p})).j.token;
 const P={anon:null,learnerNew:await L(`qalearner_${u}@example.com`,'Passw0rd!QA'),learner:await L('learner.asha@driveiq.in','password123'),instructor:await L('trainer.skyline@driveiq.in','password123'),owner1:await L('info@skylinedrive.in','password123'),owner2:await L(`owner2_${u}@example.com`,'Passw0rd!QA'),admin:await L('admin@driveiq.in','password123')};
 fs.writeFileSync('tokens.json',JSON.stringify(P));console.log(Object.entries(P).map(([k,v])=>k+':'+!!v).join(' '),reg.s,reg2.s)})();
