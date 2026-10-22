const fs=require('fs');const API='http://localhost:8000/api';
const routes=JSON.parse(fs.readFileSync('/tmp/routes.json'));
const H={'Accept':'application/json','Content-Type':'application/json'};
async function call(method,path,token,body){const h={...H};if(token)h.Authorization='Bearer '+token;
 const r=await fetch(API+path,{method,headers:h,body:body?JSON.stringify(body):undefined,redirect:'manual'});let t='';try{t=await r.text()}catch{};return {s:r.status,t}}
async function login(email,password){const r=await call('POST','/auth/login',null,{email,password});try{const j=JSON.parse(r.t);return j.token||j.data?.token}catch{return null}}
(async()=>{
 const uniq=Date.now().toString().slice(-6);
 // owner2
 const reg=await call('POST','/auth/register',null,{name:'QA Second School '+uniq,email:`owner2_${uniq}@example.com`,password:'Passw0rd!QA',role:'school',listingType:'school'});
 console.log('register owner2',reg.s,reg.t.slice(0,160));
 const P={anon:null,
  learnerNew:await login('qal2787027@example.com','Passw0rd!QA'),
  learner:await login('learner.asha@driveiq.in','password123'),
  instructor:await login('trainer.skyline@driveiq.in','password123'),
  owner1:await login('info@skylinedrive.in','password123'),
  owner2:await login(`owner2_${uniq}@example.com`,'Passw0rd!QA'),
  admin:await login('admin@driveiq.in','password123')};
 console.log(Object.entries(P).map(([k,v])=>k+':'+(v?'ok':'NO')).join(' '));
 fs.writeFileSync('tokens.json',JSON.stringify(P));
 const roleOf={learnerNew:'learner',learner:'learner',instructor:'instructor',owner1:'school',owner2:'school',admin:'admin',anon:null};
 const out=[];
 for(const r of routes){
  const methods=r.method.split('|').filter(m=>m!=='HEAD');const mw=(r.middleware||[]).join(' ');
  const needsAuth=/Authenticate:sanctum/.test(mw);
  const rm=(mw.match(/RoleMiddleware:([a-z,]+)/)||[])[1];
  const allowed=rm?rm.split(',').filter(x=>['admin','school','instructor','learner'].includes(x)):null;
  for(const m of methods){
   if(/auth\/logout|auth\/login|auth\/register|forgot|reset-password/.test(r.uri))continue;
   let path='/'+r.uri.replace(/^api\//,'').replace(/\{[^}]*(slug|token)[^}]*\??\}/gi,'baner').replace(/\{[^}]+\}/g,'1');
   path=path.replace(/\/baner(?=\/|$)/,'/skyline-driving-academy').replace(/\?$/,'');
   for(const [who,tok] of Object.entries(P)){
    if(who!=='anon'&&!tok)continue;
    const res=await call(m,path,tok,m==='GET'?undefined:{});
    const roleOK=!needsAuth?true:(who==='anon'?false:(allowed?allowed.includes(roleOf[who]):true));
    let verdict='ok';
    if(res.s>=500)verdict='SERVER_ERROR';
    else if(!roleOK&&res.s!==401&&res.s!==403)verdict='RBAC_LEAK';
    else if(needsAuth&&who==='anon'&&res.s!==401)verdict='RBAC_LEAK';
    out.push({m,path,who,s:res.s,allowed:allowed?.join('/')||(needsAuth?'any-auth':'public'),verdict,snippet:res.s>=500||verdict!=='ok'?res.t.slice(0,160):''});
   }
  }
 }
 fs.writeFileSync('rbac.json',JSON.stringify(out,null,1));
 const bad=out.filter(o=>o.verdict!=='ok');
 console.log('total checks',out.length,'issues',bad.length);
 for(const b of bad)console.log(b.verdict,b.m,b.path,'as',b.who,'->',b.s,'(allowed:'+b.allowed+')',b.snippet.replace(/\n/g,' ').slice(0,110));
})();
