const fs=require('fs');const API='http://localhost:8000/api';const P=JSON.parse(fs.readFileSync('tokens.json'));
async function call(m,path,tok,body){const h={Accept:'application/json','Content-Type':'application/json'};if(tok)h.Authorization='Bearer '+tok;const r=await fetch(API+path,{method:m,headers:h,body:body?JSON.stringify(body):undefined,redirect:'manual'});const t=await r.text();let j;try{j=JSON.parse(t)}catch{};return{s:r.status,t,j}}
const R=[];const rep=(id,ok,note)=>{R.push({id,ok,note});console.log((ok?'PASS ':'FAIL ')+id+' — '+note)};
const D='2026-10-23';
(async()=>{
 // --- Scheduling ---
 const s1=await call('POST','/schools/1/schedules',P.owner1,{instructorId:1,learnerId:1,sessionDate:D,startTime:'10:00',endTime:'11:00'});rep('B1 create session',s1.s===201,`status=${s1.s} ${s1.t.slice(0,100)}`);
 const s2=await call('POST','/schools/1/schedules',P.owner1,{instructorId:1,sessionDate:D,startTime:'10:30',endTime:'11:30',learnerName:'Walk-in'});rep('B2 overlapping session for same instructor rejected',s2.s===409||s2.s===422,`status=${s2.s} ${s2.t.slice(0,120)}`);
 const s3=await call('POST','/schools/1/schedules',P.owner1,{instructorId:2,learnerId:1,sessionDate:D,startTime:'10:15',endTime:'10:45'});rep('B3 same learner double-booked rejected',s3.s===409||s3.s===422,`status=${s3.s} ${s3.t.slice(0,120)}`);
 const s4=await call('POST','/schools/1/schedules',P.owner1,{instructorId:1,sessionDate:D,startTime:'12:00',endTime:'11:00'});rep('B4 end before start rejected',s4.s===422,`status=${s4.s} ${s4.t.slice(0,100)}`);
 const s5=await call('POST','/schools/1/schedules',P.owner1,{instructorId:1,sessionDate:'2020-01-01',startTime:'10:00',endTime:'11:00'});rep('B5 session in the past rejected',s5.s===422,`status=${s5.s} ${s5.t.slice(0,100)}`);
 const s6=await call('POST','/schools/7/schedules',P.owner2,{instructorId:1,sessionDate:D,startTime:'14:00',endTime:'15:00'});rep('B6 cross-school instructorId injection rejected',s6.s===422||s6.s===403||s6.s===404,`status=${s6.s} ${s6.t.slice(0,140)}`);
 const s7=await call('POST','/schools/7/schedules',P.owner2,{instructorId:999,vehicleId:1,learnerId:1,sessionDate:D,startTime:'14:00',endTime:'15:00'});rep('B7 cross-school vehicle/learner injection rejected',s7.s===422||s7.s===403||s7.s===404,`status=${s7.s} ${s7.t.slice(0,140)}`);
 const sa=await call('POST',`/schedules/${s1.j?.id}/attendance`,P.learner,{status:'present'});rep('B8 learner cannot mark attendance',sa.s===403,`status=${sa.s}`);
 const sb=await call('POST',`/schedules/${s1.j?.id}/attendance`,P.owner2,{status:'present'});rep('B9 other-school owner cannot mark attendance',sb.s===403||sb.s===404,`status=${sb.s}`);
 const sc=await call('POST',`/schedules/${s1.j?.id}/attendance`,P.instructor,{status:'present'});rep('B10 assigned trainer marks attendance (future session)',sc.s===200||sc.s===201||sc.s===422,`status=${sc.s} ${sc.t.slice(0,100)} (marking a FUTURE session present should arguably be rejected)`);
 // --- Vehicles ---
 const v1=await call('POST','/schools/1/vehicles',P.owner1,{registrationNumber:'MH12QA1234',type:'car',transmission:'manual'});rep('B11 add vehicle',v1.s===201,`status=${v1.s}`);
 const v2=await call('POST','/schools/1/vehicles',P.owner1,{registrationNumber:'mh12qa1234',type:'car'});rep('B12 duplicate registration (case-insens.) rejected',v2.s===422,`status=${v2.s}`);
 const v3=await call('POST','/schools/7/vehicles',P.owner2,{registrationNumber:'MH12QA1234',type:'car'});rep('B13 same reg in other school allowed? (info)',true,`status=${v3.s}`);
 const v4=await call('POST','/schools/1/vehicles',P.owner1,{registrationNumber:'X',type:'rocket',year:1800});rep('B14 bad vehicle type/year rejected',v4.s===422,`status=${v4.s}`);
 // --- Packages & payments ---
 const pk=await call('GET','/packages?schoolId=1',null);const pkg=(pk.j||[])[0];console.log('package sample',JSON.stringify(pkg).slice(0,200));
 const np=await call('POST','/packages',P.owner1,{schoolId:1,name:'QA Neg',price:-500,sessions:5,durationDays:10,vehicleType:'car'});rep('B15 negative package price rejected',np.s===422,`status=${np.s} ${np.t.slice(0,120)}`);
 const pu=await call('POST','/schools/1/payments/package',P.owner1,{learnerId:1,packageId:pkg?.id||1,method:'cash',markPaid:false});rep('B16 purchase package (pending)',pu.s===201||pu.s===200,`status=${pu.s} ${pu.t.slice(0,160)}`);
 const payId=pu.j?.payment?.id??pu.j?.id;
 if(payId){const m1=await call('POST',`/payments/${payId}/mark-paid`,P.owner1,{});const m2=await call('POST',`/payments/${payId}/mark-paid`,P.owner1,{});const m3=await call('POST',`/payments/${payId}/mark-failed`,P.owner1,{});
  rep('B17 mark-paid then paid again is idempotent/blocked',m1.s===200&&(m2.s===200||m2.s===409||m2.s===422),`first=${m1.s} second=${m2.s}`);
  rep('B18 paid payment cannot be marked failed',m3.s===409||m3.s===422||m3.s===403,`status=${m3.s} ${m3.t.slice(0,100)}`);
  const m4=await call('POST',`/payments/${payId}/mark-paid`,P.instructor,{});rep('B19 trainer cannot mark paid',m4.s===403,`status=${m4.s}`);}
 const pc=await call('POST','/schools/1/payments/package',P.owner2,{learnerId:1,packageId:1});rep('B20 other school cannot bill school1 learner',pc.s===403||pc.s===404||pc.s===422,`status=${pc.s}`);
 // --- Leave ---
 const lv=await call('POST','/instructor/leave-requests',P.instructor,{startDate:D,endDate:D,reason:'QA leave'});rep('B21 trainer requests leave',lv.s===201||lv.s===200,`status=${lv.s} ${lv.t.slice(0,140)}`);
 const lv2=await call('POST','/instructor/leave-requests',P.instructor,{startDate:'2020-01-01',endDate:'2020-01-02'});rep('B22 leave in past rejected',lv2.s===422,`status=${lv2.s}`);
 const lv3=await call('POST','/instructor/leave-requests',P.instructor,{startDate:'2026-11-05',endDate:'2026-11-01'});rep('B23 leave end<start rejected',lv3.s===422,`status=${lv3.s}`);
 const ll=await call('GET','/schools/1/leave-requests',P.owner1);const lreq=(ll.j||[]).find(x=>x.reason==='QA leave');console.log('leave list',ll.s,JSON.stringify(lreq).slice(0,300));
 if(lreq){const ap=await call('PATCH',`/leave-requests/${lreq.id}`,P.owner1,{status:'approved'});rep('B24 owner approves leave',ap.s===200,`status=${ap.s} ${ap.t.slice(0,200)}`);
  const ap2=await call('PATCH',`/leave-requests/${lreq.id}`,P.instructor,{status:'approved'});rep('B25 trainer cannot self-approve leave',ap2.s===403,`status=${ap2.s}`);}
 // --- Inquiry lifecycle ---
 const inq=await call('GET','/inquiries',P.owner1);const i1=(inq.j||[])[0];
 if(i1){for(const st of ['converted','bogus','interested']){const r=await call('PATCH',`/inquiries/${i1.id}`,P.owner1,{status:st});console.log('inquiry PATCH',st,r.s,r.t.slice(0,90))}
  const nt=await call('POST',`/inquiries/${i1.id}/notes`,P.owner1,{body:'<script>alert(1)</script> note',note:'<b>x</b>'});rep('B26 add note',nt.s===201||nt.s===200,`status=${nt.s} ${nt.t.slice(0,100)}`);}
 // --- Reviews ---
 const anonPend=await call('GET','/reviews?schoolId=1&includePending=1',null);const pendCnt=(anonPend.j||[]).filter(x=>x.moderationStatus==='pending'||x.status==='pending').length;rep('B27 anon includePending=1 must not expose pending reviews',pendCnt===0,`status=${anonPend.s} pending visible=${pendCnt} total=${(anonPend.j||[]).length}`);
 const rv1=await call('POST','/reviews',P.learnerNew,{schoolId:1,authorName:'QA',rating:5,content:'great'});rep('B28 ineligible learner review rejected (server-side)',rv1.s===403||rv1.s===422,`status=${rv1.s} ${rv1.t.slice(0,120)}`);
 const rv0=await call('POST','/reviews',null,{schoolId:1,authorName:'QA',rating:5,content:'anon'});rep('B29 anonymous review rejected',rv0.s===401,`status=${rv0.s}`);
 const rv2=await call('POST','/reviews',P.learner,{schoolId:1,authorName:'Asha',rating:9,content:'bad rating'});rep('B30 rating 9 rejected',rv2.s===422,`status=${rv2.s}`);
 const rv3=await call('POST','/reviews',P.learner,{schoolId:1,authorName:'Asha',rating:4,title:'t',content:'<img src=x onerror=alert(1)> QA review'});rep('B31 enrolled learner can review',rv3.s===201||rv3.s===200,`status=${rv3.s} ${rv3.t.slice(0,140)}`);
 const rv4=await call('POST','/reviews',P.learner,{schoolId:1,authorName:'Asha',rating:4,content:'dup review'});rep('B32 duplicate review by same learner rejected',rv4.s===409||rv4.s===422||rv4.s===403,`status=${rv4.s} ${rv4.t.slice(0,100)}`);
 const rid=rv3.j?.id;
 if(rid){let codes=[];for(const who of ['learnerNew','instructor','owner2','admin']){codes.push((await call('POST',`/reviews/${rid}/report`,P[who],{reason:'spam'})).s)}console.log('report codes',codes);const rg=await call('GET','/reviews?schoolId=1',null);rep('B33 review auto-hidden after 3 reports',!(rg.j||[]).some(x=>x.id===rid),`still public=${(rg.j||[]).some(x=>x.id===rid)}`);
  const own=await call('POST',`/reviews/${rid}/report`,P.owner2,{reason:'spam'});rep('B34 duplicate report by same user blocked',own.s===409||own.s===422||own.s===200,`status=${own.s}`);
  const del=await call('DELETE',`/reviews/${rid}`,P.owner1);rep('B35 school owner cannot delete reviews (admin-only)',del.s===403,`status=${del.s}`);}
 // --- Admin user lifecycle ---
 const us=await call('GET','/admin/users?search=rahul',P.admin);const u=(us.j?.data||us.j||[]).find?.(x=>x.email==='rahul@gmail.com');console.log('admin users',us.s,u?u.id:JSON.stringify(us.j).slice(0,150));
 if(u){const tk=(await call('POST','/auth/login',null,{email:'rahul@gmail.com',password:'password123'})).j?.token;
  const de=await call('PATCH',`/admin/users/${u.id}`,P.admin,{deactivated:true,active:false,isActive:false,status:'deactivated'});console.log('deactivate',de.s,de.t.slice(0,160));
  const me=await call('GET','/auth/me',tk);rep('B36 deactivated user token revoked',me.s===401||me.s===403,`status=${me.s}`);
  const lg=await call('POST','/auth/login',null,{email:'rahul@gmail.com',password:'password123'});rep('B37 deactivated user cannot log in',lg.s>=400,`status=${lg.s}`);
  const re=await call('PATCH',`/admin/users/${u.id}`,P.admin,{deactivated:false,active:true,isActive:true,status:'active'});console.log('reactivate',re.s);
  const selfd=await call('PATCH','/admin/users/4',P.admin,{deactivated:true,active:false,isActive:false});rep('B38 admin cannot deactivate self (lockout)',selfd.s>=400,`status=${selfd.s} ${selfd.t.slice(0,100)}`);await call('PATCH','/admin/users/4',P.admin,{deactivated:false,active:true});}
 // --- Publishing gate ---
 const pg=await call('PATCH','/admin/schools/7/listing-status',P.admin,{listingStatus:'published',status:'published'});console.log('admin publish unverified-owner school',pg.s,pg.t.slice(0,160));
 const ent=await call('GET','/schools/1/entitlements',P.owner1);console.log('entitlements',ent.s,ent.t.slice(0,220));
 fs.writeFileSync('biz.json',JSON.stringify(R,null,1));
})();
