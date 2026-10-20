const { chromium } = require('playwright');const BASE='http://localhost:5173';
(async()=>{const b=await chromium.launch();
 const ctx=await b.newContext({viewport:{width:1366,height:850},geolocation:{latitude:18.5590,longitude:73.7868},permissions:['geolocation']});const p=await ctx.newPage();
 const reqs=[];p.on('request',r=>{if(r.url().includes('/api/schools?'))reqs.push(r.url().replace('http://localhost:8000',''))});
 await p.goto(BASE+'/search',{waitUntil:'networkidle'});await p.getByRole('button',{name:'Accept',exact:true}).click({timeout:800}).catch(()=>{});
 await p.getByRole('button',{name:'Use my location'}).click();await p.getByRole('button',{name:'Continue'}).click();await p.waitForTimeout(2500);
 console.log('geo req:',reqs.filter(r=>/lat=/.test(r)).slice(-1)[0]);console.log('header:',await p.locator('h1,h2').first().innerText(),'|',(await p.innerText('main').catch(()=>'')).match(/\d+ schools? found/)?.[0]);
 const firstCards=await p.locator('[data-testid^=card-school]').evaluateAll(e=>e.slice(0,5).map(x=>x.innerText.replace(/\n/g,' ').slice(0,90)));console.log(firstCards.join('\n'));
 await p.screenshot({path:'shots2/geo-nearme-result.png'});
 // pagination at bottom of /search
 await p.goto(BASE+'/search',{waitUntil:'networkidle'});await p.evaluate(()=>window.scrollTo(0,document.body.scrollHeight));await p.waitForTimeout(500);
 console.log('pagination controls:',await p.getByRole('button',{name:/next|load more|page/i}).count(),'| count label:',(await p.innerText('body')).match(/\d+ schools? found/)?.[0]);
 await b.close()})();
