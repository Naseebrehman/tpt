// Development-only browser checks against a local preview or populated staging site.
const {chromium}=require('playwright');
const base=(process.env.TPT_TEST_BASE_URL||'http://127.0.0.1:8765').replace(/\/$/,'');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.TPT_CHROMIUM_EXECUTABLE||undefined,args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--hide-scrollbars']});
 const page=await browser.newPage();let count=0;
 function check(ok,name){if(!ok)throw Error(name);count++;console.log('PASS',name)}
 await page.route('**/*',route=>route.request().url().startsWith(base)?route.continue():route.abort());
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 for(const width of [320,390,768,1280]){
  await page.setViewportSize({width,height:844});
  await page.goto(base+'/');await page.locator('#preloader').waitFor({state:'detached'});
  check(await page.evaluate(()=>getComputedStyle(document.body).backgroundColor==='rgb(24, 25, 30)'),'soft charcoal background '+width);
  check(await page.locator('.system').evaluate(e=>getComputedStyle(e).backgroundColor==='rgb(32, 33, 39)'),'dark System '+width);
  check(await page.locator('.industry-tab').count()===11,'eleven industries '+width);
  check(await page.locator('.industry-detail:visible').count()===1,'single industry panel '+width);
  await page.locator('.industry-tab').filter({hasText:'Technology'}).click();
  check((await page.locator('.industry-detail:visible h3').textContent())==='Technology','industry click changes content '+width);
  await page.keyboard.press('End');
  check((await page.locator('.industry-detail:visible h3').textContent())==='Local Businesses','industry End key '+width);
  await page.keyboard.press('Home');
  check((await page.locator('.industry-detail:visible h3').textContent())==='Roofing','industry Home key '+width);
  await page.locator('.founder-avatar').scrollIntoViewIfNeeded();
  await page.locator('.founder-avatar').evaluate(e=>e.decode());
  check(await page.locator('.founder-avatar').evaluate(e=>e.complete&&e.naturalWidth>0&&e.getBoundingClientRect().width<=80),'small founder portrait '+width);
  check(await page.locator('.founder-compact').evaluate(e=>e.getBoundingClientRect().height)<(width>=1000?250:410),'compact founder section '+width);
  check(await page.locator('.footer-compact').evaluate(e=>e.getBoundingClientRect().height)<(width>=1000?320:610),'compact footer '+width);
  check(await page.locator('.footer-socials a').count()>=3,'footer social links '+width);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'home fits viewport '+width);
  if(width===1280){await page.locator('.industry-explorer').screenshot({path:'/tmp/tpt-industries.png'});await page.locator('.founder-compact').screenshot({path:'/tmp/tpt-founder.png'});await page.locator('.footer-compact').screenshot({path:'/tmp/tpt-footer.png'});}
  await page.goto(base+'/contact/');await page.locator('#preloader').waitFor({state:'detached'});
  check(await page.locator('.info-icon').count()>=5,'contact icon containers '+width);
  check(await page.locator('.info-icon > svg').evaluateAll(es=>es.every(e=>{const c=getComputedStyle(e);return e.getBoundingClientRect().width===22&&c.padding==='0px'&&c.borderTopWidth==='0px';})),'contact SVGs not double padded '+width);
  check(await page.locator('.info-card .footer-socials svg').evaluateAll(es=>es.length>=3&&es.every(e=>e.getBoundingClientRect().width===18)),'contact social icons '+width);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'contact fits viewport '+width);
  if(width===390)await page.locator('.contact-side').screenshot({path:'/tmp/tpt-contact-mobile.png'});
  await page.goto(base+'/about/');await page.locator('#preloader').waitFor({state:'detached'});
  check(await page.locator('.team-card').count()===3,'three team profiles '+width);
  check((await page.locator('.team-card').first().innerText()).includes('Founder'),'team includes founder '+width);
  await page.locator('.team-grid').scrollIntoViewIfNeeded();
  await page.locator('.team-photo img').evaluateAll(es=>Promise.all(es.map(e=>e.decode())));
  check(await page.locator('.team-photo img').evaluateAll(es=>es.every(e=>e.complete&&e.naturalWidth>0)),'team photo loads '+width);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'about fits viewport '+width);
  if(width===1280)await page.locator('.team-grid').screenshot({path:'/tmp/tpt-team.png'});
 }
 check(errors.length===0,'no browser runtime errors');
 console.log(count+' interface checks passed');await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
