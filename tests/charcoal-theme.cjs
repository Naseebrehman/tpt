// Development-only: run against the local fixture or a staging site.
const {chromium}=require('playwright');
const base=(process.env.TPT_TEST_BASE_URL||'http://127.0.0.1:8765').replace(/\/$/,'');
function luminance(hex){const rgb=hex.replace('#','').match(/../g).map(v=>parseInt(v,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return rgb[0]*.2126+rgb[1]*.7152+rgb[2]*.0722;}
function contrast(a,b){const x=luminance(a),y=luminance(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);}
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.TPT_CHROMIUM_EXECUTABLE||undefined,args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--hide-scrollbars']});
 try{
  const page=await browser.newPage();let checks=0;
  function check(ok,name){if(!ok)throw Error(name);checks++;console.log('PASS',name);}
  await page.route('**/*',r=>r.request().url().startsWith(base)?r.continue():r.abort());
  await page.goto(base+'/');await page.locator('#preloader').waitFor({state:'detached'});
  const tokens=await page.evaluate(()=>{const s=getComputedStyle(document.documentElement);return Object.fromEntries(['bg','bg-soft','surface','surface-2','text','muted','muted-2','violet','violet-2','violet-soft','cyan'].map(k=>[k,s.getPropertyValue('--'+k).trim()]));});
  for(const text of ['text','muted','muted-2'])for(const bg of ['bg','bg-soft','surface','surface-2'])check(contrast(tokens[text],tokens[bg])>=4.5,text+' on '+bg+' meets 4.5:1');
  for(const [key,value] of Object.entries({violet:'#7c3aed','violet-2':'#8b5cf6','violet-soft':'#a78bfa',cyan:'#22d3ee'}))check(tokens[key]===value,key+' brand accent unchanged');
  check(luminance(tokens.bg)<luminance(tokens['bg-soft'])&&luminance(tokens['bg-soft'])<luminance(tokens.surface)&&luminance(tokens.surface)<luminance(tokens['surface-2']),'layered dark surfaces');
  check(await page.locator('.hero-visual').evaluate(e=>getComputedStyle(e,'::after').backgroundImage.includes('0.32')),'lighter hero photo overlay');
  for(const width of [320,768,1440]){
   await page.setViewportSize({width,height:900});
   check(await page.locator('.site-footer').evaluate(e=>getComputedStyle(e).backgroundColor==='rgb(22, 23, 28)'),'charcoal footer '+width);
   await page.locator('#chatbotFab').click();
   check(await page.locator('.chatbot-window').evaluate(e=>getComputedStyle(e).backgroundColor==='rgb(38, 40, 48)'),'Alia charcoal surface '+width);
   await page.locator('#chatbotFab').click();
  }
  await page.goto(base+'/contact/');await page.locator('#preloader').waitFor({state:'detached'});
  check(await page.locator('.field input').first().evaluate(e=>getComputedStyle(e).backgroundColor==='rgb(32, 33, 39)'),'charcoal form fields');
  check(await page.locator('.field input').first().evaluate(e=>getComputedStyle(e,'::placeholder').color==='rgb(162, 168, 184)'),'readable placeholders');
  console.log(checks+' charcoal theme checks passed');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
