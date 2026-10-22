const fs=require('fs');const API='http://localhost:8000/api';const P=JSON.parse(fs.readFileSync('tokens.json'));
async function call(m,path,tok,body){const h={Accept:'application/json','Content-Type':'application/json'};if(tok)h.Authorization='Bearer '+tok;const r=await fetch(API+path,{method:m,headers:h,body:body?JSON.stringify(body):undefined,redirect:'manual'});return{s:r.status,t:await r.text()}}
const ids=(t)=>{try{const j=JSON.parse(t);const a=Array.isArray(j)?j:(j.data||j.items||[]);return Array.isArray(a)?a.map(x=>x.id).filter(Boolean):[]}catch{return[]}};
(async()=>{
 const g={};
 const cols={inquiries:'/inquiries',instructors:'/schools/1/instructors',learners:'/schools/1/learners',vehicles:'/schools/1/vehicles',schedules:'/schools/1/schedules',payments:'/schools/1/payments',leave:'/schools/1/leave-requests',team:'/schools/1/team',packages:'/packages?schoolId=1',invoices:'/schools/1/billing/invoices',threads:'/messages/threads'};
 for(const [k,p] of Object.entries(cols)){const r=await call('GET',p,P.owner1);g[k]=ids(r.t);console.log(k,r.s,g[k].slice(0,5))}
 const A=(k)=>g[k][0]??1;
 const tests=[ // [method,path,body,desc]
  ['GET','/schools/1/dashboard'],['GET','/schools/1/learners'],['GET','/schools/1/payments'],['GET','/schools/1/instructors'],['GET','/schools/1/vehicles'],['GET','/schools/1/schedules'],['GET','/schools/1/leave-requests'],['GET','/schools/1/team'],['GET','/schools/1/settings'],['GET','/schools/1/audit-logs'],['GET','/schools/1/analytics'],['GET','/schools/1/analytics/instructors'],['GET','/schools/1/subscription'],['GET','/schools/1/entitlements'],['GET','/schools/1/billing/invoices'],['GET','/schools/1/google-business'],['GET','/stats/school/1'],['GET','/inquiries?schoolId=1'],
  ['GET',`/inquiries/${A('inquiries')}/timeline`],['PATCH',`/inquiries/${A('inquiries')}`,{status:'lost'}],['POST',`/inquiries/${A('inquiries')}/notes`,{note:'pwn',body:'pwn'}],['POST',`/inquiries/${A('inquiries')}/convert`,{}],
  ['GET',`/instructors/${A('instructors')}`],['PATCH',`/instructors/${A('instructors')}`,{name:'PWNED'}],['DELETE',`/instructors/${A('instructors')}`],['GET',`/instructors/${A('instructors')}/documents`],['POST',`/instructors/${A('instructors')}/login`,{}],
  ['GET',`/learners/${A('learners')}`],['PATCH',`/learners/${A('learners')}`,{name:'PWNED'}],['POST',`/learners/${A('learners')}/assign`,{}],['GET',`/learners/${A('learners')}/documents`],['GET',`/learners/${A('learners')}/progress`],['PUT',`/learners/${A('learners')}/progress`,{}],['GET',`/learners/${A('learners')}/sessions`],['GET',`/learners/${A('learners')}/driving-tests`],['GET',`/learners/${A('learners')}/assignments`],
  ['PATCH',`/vehicles/${A('vehicles')}`,{name:'PWNED'}],['GET',`/vehicles/${A('vehicles')}/documents`],
  ['PATCH',`/schedules/${A('schedules')}`,{status:'cancelled'}],['POST',`/schedules/${A('schedules')}/attendance`,{}],
  ['POST',`/payments/${A('payments')}/mark-paid`,{}],['POST',`/payments/${A('payments')}/mark-failed`,{}],
  ['PATCH',`/leave-requests/${A('leave')}`,{status:'approved'}],
  ['PATCH',`/packages/${A('packages')}`,{name:'PWNED'}],['DELETE',`/packages/${A('packages')}`],
  ['PATCH','/schools/1',{name:'PWNED'}],['PUT','/schools/1/settings',{}],['POST','/schools/1/team',{email:'x@example.com',role:'manager'}],['DELETE',`/schools/1/team/${A('team')}`],
  ['POST','/schools/1/learners',{name:'pwn',mobile:'9999999999'}],['POST','/schools/1/vehicles',{}],['POST','/schools/1/instructors',{}],['POST','/schools/1/schedules',{}],['POST','/schools/1/payments/package',{}],['POST','/schools/1/billing/invoices',{}],
  ['GET',`/messages/threads/${A('threads')}`],['POST',`/messages/threads/${A('threads')}/read`,{}],
  ['GET',`/schools/1/billing/invoices/${A('invoices')}`],['POST',`/schools/1/billing/invoices/${A('invoices')}/cancel`,{}],
 ];
 const out=[];
 for(const who of ['owner2','learnerNew','learner','instructor']){
  for(const [m,p,b] of tests){
   // skip destructive for roles that legitimately may use learner/instructor scoped routes? keep all; check status
   const r=await call(m,p,P[who],b);
   const bad=r.s<300; // 2xx for foreign role is a leak
   out.push({who,m,p,s:r.s,bad,snip:bad?r.t.slice(0,140):''});
  }
 }
 fs.writeFileSync('idor.json',JSON.stringify(out,null,1));
 console.log('checks',out.length,'leaks',out.filter(o=>o.bad).length);
 for(const o of out.filter(o=>o.bad))console.log('LEAK',o.who,o.m,o.p,o.s,o.snip.replace(/\n/g,' '));
 console.log('statuses by who:',['owner2','learnerNew','learner','instructor'].map(w=>w+':'+JSON.stringify(out.filter(o=>o.who===w).reduce((a,o)=>(a[o.s]=(a[o.s]||0)+1,a),{}))).join(' '));
})();
