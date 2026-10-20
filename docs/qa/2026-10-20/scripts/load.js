const fs=require('fs');const API='http://localhost:8000/api';const P=JSON.parse(fs.readFileSync('tokens.json'));
const label=process.argv[2]||'run';
const sc=[
 ['healthz','/healthz'],
 ['schools list','/schools'],
 ['geo 5km','/schools?lat=18.52&lng=73.85&radiusKm=5'],
 ['geo 10km+filters','/schools?lat=18.52&lng=73.85&radiusKm=10&vehicleType=car&minRating=4&sort=distance'],
 ['school by slug','/schools/slug/skyline-driving-academy'],
 ['featured','/schools/featured'],
 ['dashboard(owner)','/schools/1/dashboard',P.owner1],
 ['leads list 20k(owner)','/inquiries',P.owner1],
 ['learners(owner)','/schools/1/learners',P.owner1],
];
const conc=(process.argv[3]||'1,10,40').split(',').map(Number);const dur=+(process.argv[4]||8)*1000;
function pct(a,p){a.sort((x,y)=>x-y);return a[Math.min(a.length-1,Math.floor(a.length*p))]}
async function run(path,tok,c){const lat=[];const codes={};let bytes=0;const end=Date.now()+dur;
 const w=async()=>{while(Date.now()<end){const t=performance.now();try{const r=await fetch(API+path,{headers:{Accept:'application/json',...(tok?{Authorization:'Bearer '+tok}:{})}});const b=await r.arrayBuffer();bytes=b.byteLength;codes[r.status]=(codes[r.status]||0)+1}catch(e){codes.ERR=(codes.ERR||0)+1}lat.push(performance.now()-t)}};
 await Promise.all(Array.from({length:c},w));return{n:lat.length,rps:(lat.length/(dur/1000)).toFixed(1),p50:pct(lat,.5).toFixed(0),p95:pct(lat,.95).toFixed(0),p99:pct(lat,.99).toFixed(0),max:Math.max(...lat).toFixed(0),codes:JSON.stringify(codes),kb:(bytes/1024).toFixed(0)}}
(async()=>{console.log('## '+label);console.log('scenario'.padEnd(24),'conc','rps'.padStart(7),'p50'.padStart(6),'p95'.padStart(6),'p99'.padStart(6),'max'.padStart(6),'respKB'.padStart(7),'codes');
 const res=[];for(const [n,p,t] of sc){for(const c of conc){const r=await run(p,t,c);console.log(n.padEnd(24),String(c).padStart(4),r.rps.padStart(7),r.p50.padStart(6),r.p95.padStart(6),r.p99.padStart(6),r.max.padStart(6),r.kb.padStart(7),r.codes);res.push({n,c,...r})}}
 fs.writeFileSync(`load-${label}.json`,JSON.stringify(res,null,1))})();
