const { chromium } = require('playwright');
const pages=['/','/search','/school/skyline-driving-academy','/auth/login','/for-schools'];
(async()=>{const b=await chromium.launch();
 for(const prof of [{n:'desktop-nothrottle',vp:{width:1366,height:800},th:null},{n:'mobile-slow4G',vp:{width:390,height:844},th:{dl:1.6*1024*1024/8,ul:750*1024/8,lat:150},cpu:4}]){
  for(const pg of pages){
   const ctx=await b.newContext({viewport:prof.vp,userAgent:prof.th?'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36':undefined});
   const p=await ctx.newPage();const cdp=await ctx.newCDPSession(p);
   if(prof.th){await cdp.send('Network.enable');await cdp.send('Network.emulateNetworkConditions',{offline:false,downloadThroughput:prof.th.dl,uploadThroughput:prof.th.ul,latency:prof.th.lat});await cdp.send('Emulation.setCPUThrottlingRate',{rate:prof.cpu});}
   await p.addInitScript(()=>{window.__lcp=0;window.__cls=0;new PerformanceObserver(l=>{for(const e of l.getEntries())window.__lcp=e.startTime}).observe({type:'largest-contentful-paint',buffered:true});new PerformanceObserver(l=>{for(const e of l.getEntries())if(!e.hadRecentInput)window.__cls+=e.value}).observe({type:'layout-shift',buffered:true})});
   let bytes=0;p.on('response',async r=>{try{const h=r.headers()['content-length'];if(h)bytes+=+h}catch{}});
   const t=Date.now();await p.goto('http://localhost:4173'+pg,{waitUntil:'load',timeout:90000}).catch(()=>{});await p.waitForTimeout(4000);
   const m=await p.evaluate(()=>{const n=performance.getEntriesByType('navigation')[0];const fcp=performance.getEntriesByName('first-contentful-paint')[0];return{lcp:Math.round(window.__lcp),cls:+window.__cls.toFixed(3),fcp:Math.round(fcp?.startTime||0),dcl:Math.round(n.domContentLoadedEventEnd),load:Math.round(n.loadEventEnd),transferKB:Math.round(performance.getEntriesByType('resource').reduce((a,r)=>a+(r.transferSize||0),0)/1024)}});
   console.log(prof.n.padEnd(20),pg.padEnd(34),JSON.stringify(m));await ctx.close();}}
 await b.close();})();
