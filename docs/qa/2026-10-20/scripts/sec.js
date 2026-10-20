const fs=require('fs');const API='http://localhost:8000/api';const P=JSON.parse(fs.readFileSync('tokens.json'));
async function call(m,path,tok,body,extra={}){const h={Accept:'application/json','Content-Type':'application/json',...extra};if(tok)h.Authorization='Bearer '+tok;const r=await fetch(API+path,{method:m,headers:h,body:body?JSON.stringify(body):undefined,redirect:'manual'});const t=await r.text();let j;try{j=JSON.parse(t)}catch{};return{s:r.status,t,j,h:r.headers}}
const R=[];const rep=(id,ok,note)=>{R.push({id,ok,note});console.log((ok?'PASS ':'FAIL ')+id+' — '+note)};
(async()=>{
 // owner2's school id
 const me2=await call('GET','/auth/me',P.owner2);const s2=me2.j.schoolId??me2.j.user?.school_id;console.log('owner2 school',s2);
 // S1 self-verify / rating tampering by school owner
 const before=await call('GET','/schools/'+s2,null);
 const r=await call('PATCH','/schools/'+s2,P.owner2,{verified:true,phoneVerified:true,businessVerified:true,locationVerified:true,premiumVerified:true,rating:5,reviewCount:999});
 const after=await call('GET','/schools/'+s2,null);
 const a=after.j||{};
 rep('S1 owner cannot self-verify / fake rating',!(a.verified||a.premiumVerified||a.rating==5||a.reviewCount==999),`status=${r.s} verified=${a.verified} premium=${a.premiumVerified} rating=${a.rating} reviewCount=${a.reviewCount} (before verified=${before.j?.verified})`);
 // S2 mass assignment: tier / featured / listingStatus / school_id
 const r2=await call('PATCH','/schools/'+s2,P.owner2,{listingStatus:'published',plan:'premium',featured:true,isFeatured:true,school_id:1,ownerId:4,emailVerified:true});
 const a2=(await call('GET','/schools/'+s2,null)).j||{};
 rep('S2 owner cannot publish/feature via mass assignment',!(a2.featured||a2.isFeatured||a2.plan==='premium'),`status=${r2.s} listingStatus=${a2.listingStatus} featured=${a2.featured}/${a2.isFeatured} keys=${Object.keys(a2).length}`);
 // S3 unverified-owner publishing gate (public listing)
 const pub=await call('GET','/schools?q=QA%20Second');rep('S3 unverified/new school not publicly listed',!JSON.stringify(pub.j).includes('QA Second'),`status=${pub.s}`);
 // S4 SQLi probes on public search
 const inj=["' OR 1=1--","1;DROP TABLE schools--","baner' UNION SELECT null--"];
 let sqlOK=true,note=[];for(const q of inj){for(const par of ['q','locality','sort','vehicleType','minRating','lat','lng','radiusKm','maxPrice']){const x=await call('GET',`/schools?${par}=${encodeURIComponent(q)}`);if(x.s>=500){sqlOK=false;note.push(par+':'+x.s+' '+x.t.slice(0,90))}}}
 rep('S4 SQLi probes on /schools do not 500',sqlOK,note.join(' | ')||'all 4xx/2xx');
 // S5 geo param bounds
 const g=await call('GET','/schools?lat=999&lng=999&radiusKm=99999999');rep('S5 absurd geo params handled',g.s<500,`status=${g.s}`);
 const g2=await call('GET','/schools?lat=abc&lng=xyz&radiusKm=-5');rep('S5b invalid geo params handled',g2.s<500,`status=${g2.s} ${g2.t.slice(0,100)}`);
 // S6 CORS
 const c=await fetch(API+'/schools',{headers:{Origin:'https://evil.example'}});rep('S6 CORS does not allow arbitrary origin',c.headers.get('access-control-allow-origin')!=='https://evil.example'&&c.headers.get('access-control-allow-origin')!=='*',`ACAO=${c.headers.get('access-control-allow-origin')} creds=${c.headers.get('access-control-allow-credentials')}`);
 // S7 security headers
 const hh=c.headers;rep('S7 security headers present (nosniff/frame/HSTS/CSP)',['x-content-type-options','x-frame-options','strict-transport-security','content-security-policy'].every(k=>hh.get(k)),['x-content-type-options','x-frame-options','strict-transport-security','content-security-policy','referrer-policy','permissions-policy'].map(k=>k+'='+(hh.get(k)?'yes':'NO')).join(' '));
 // S8 debug info leak
 const e=await call('GET','/notifications/abc/read',P.owner1);const e2=await call('POST','/notifications/abc/read',P.owner1,{});rep('S8 errors don\'t leak SQL/stack (APP_DEBUG)',!/SQLSTATE|vendor\/laravel|Stack trace/.test(e2.t),`status=${e2.s} ${e2.t.slice(0,80).replace(/\n/g,' ')}`);
 // S9 404 on unknown API route is JSON
 const nf=await call('GET','/does-not-exist');rep('S9 unknown API route → JSON 404',nf.s===404&&!!nf.j,`status=${nf.s}`);
 // S10 stored XSS payload round trip (inquiry)
 const xss='<img src=x onerror=alert(1)>';
 await new Promise(r=>setTimeout(r,3100));
 const inq=await call('POST','/inquiries',null,{schoolId:1,name:xss,phone:'9876543210',vehicleType:'car',message:xss,formStartedAt:Date.now()-5000});
 rep('S10 inquiry accepts XSS text as data (output encoding is UI job)',inq.s===201||inq.s===200,`status=${inq.s} ${inq.t.slice(0,100)}`);
 // S11 inquiry validation edge: overlong, unicode, negative school
 const big=await call('POST','/inquiries',null,{schoolId:1,name:'x'.repeat(300),phone:'1',vehicleType:'car',formStartedAt:Date.now()-5000});rep('S11 overlong name/short phone rejected',big.s===422,`status=${big.s}`);
 const ph=await call('POST','/inquiries',null,{schoolId:1,name:'QA',phone:'abc',vehicleType:'car',formStartedAt:Date.now()-5000});rep('S11b non-numeric phone rejected',ph.s===422,`status=${ph.s} (phone rule is only string|max:255)`);
 // S12 honeypot / too fast
 const fast=await call('POST','/inquiries',null,{schoolId:1,name:'QA',phone:'9876543210',vehicleType:'car',formStartedAt:Date.now()});rep('S12 too-fast submission rejected',fast.s===422,`status=${fast.s}`);
 const hp=await call('POST','/inquiries',null,{schoolId:1,name:'QA',phone:'9876543210',vehicleType:'car',website:'http://spam',formStartedAt:Date.now()-9000});rep('S12b honeypot field rejected',hp.s===422,`status=${hp.s}`);
 // S13 logout invalidates token
 const L=await call('POST','/auth/login',null,{email:'rahul@gmail.com',password:'password123'});const tk=L.j.token;
 await call('POST','/auth/logout',tk);const after2=await call('GET','/auth/me',tk);rep('S13 token invalid after logout',after2.s===401,`status=${after2.s}`);
 // S14 login brute-force throttle
 let codes=[];for(let i=0;i<12;i++){codes.push((await call('POST','/auth/login',null,{email:'admin@driveiq.in',password:'bad'+i})).s)}rep('S14 login brute force throttled (429)',codes.includes(429),codes.join(','));
 // S15 user enumeration
 const f1=await call('POST','/auth/forgot-password',null,{email:'admin@driveiq.in'});const f2=await call('POST','/auth/forgot-password',null,{email:'nobody'+Date.now()+'@example.com'});rep('S15 forgot-password does not enumerate users',f1.s===f2.s&&f1.t===f2.t,`known=${f1.s} unknown=${f2.s}`);
 const l1=await call('POST','/auth/login',null,{email:'admin@driveiq.in',password:'x'});const l2=await call('POST','/auth/login',null,{email:'nouser@example.com',password:'x'});rep('S15b login error identical for known/unknown',l1.t===l2.t||(l1.s===l2.s&&JSON.stringify(l1.j?.errors)===JSON.stringify(l2.j?.errors)),`${l1.s}/${l2.s}`);
 // S16 register as admin / injected fields
 const ra=await call('POST','/auth/register',null,{name:'Evil',email:`evil${Date.now()}@example.com`,password:'Passw0rd!QA',role:'admin'});rep('S16 cannot self-register as admin',ra.s===422,`status=${ra.s}`);
 // S17 weak password server side
 const wp=await call('POST','/auth/register',null,{name:'Weak',email:`weak${Date.now()}@example.com`,password:'password',role:'learner'});rep('S17 weak password rejected server-side',wp.s===422,`status=${wp.s}`);
 // S18 token type / malformed bearer
 const mb=await call('GET','/auth/me','garbage');rep('S18 garbage bearer → 401',mb.s===401,`status=${mb.s}`);
 // S19 large body / JSON depth
 const lb=await call('POST','/contact',null,{name:'a',email:'a@b.co',subject:'s',message:'m'.repeat(2_000_000),formStartedAt:Date.now()-9000});rep('S19 2MB contact message rejected (no max)',lb.s===413||lb.s===422,`status=${lb.s}`);
 // S20 admin creds default
 fs.writeFileSync('sec.json',JSON.stringify(R,null,1));
})();
