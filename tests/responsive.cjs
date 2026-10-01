// Optional development test: NODE_PATH=/path/to/dev/node_modules node tests/responsive.cjs
const {chromium}=require('playwright');
const fs=require('fs'), path=require('path'), os=require('os');
const base=(process.env.TPT_TEST_BASE_URL || 'http://127.0.0.1:8765').replace(/\/$/,'');
const screenshotDir=process.env.TPT_SCREENSHOT_DIR || path.join(os.tmpdir(),'tpt-layout-tests');
fs.mkdirSync(screenshotDir,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true, executablePath:process.env.TPT_CHROMIUM_EXECUTABLE || undefined, args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--hide-scrollbars']});
 let checks=0; function check(value,msg){if(!value)throw Error(msg);checks++;console.log('PASS',msg)}
 const page=await browser.newPage();
 for(const width of [320,390,768,1280,1440]){
  await page.setViewportSize({width,height:width===320?568:844});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',r=>r.request().url().startsWith(base)?r.continue():r.abort());
  await page.goto(base+'/');await page.waitForTimeout(1300);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'home no overflow '+width);
  check(await page.locator('.acc-item.open').count()===0,'accordions closed '+width);
  await page.locator('.services-acc .acc-head').first().click();await page.waitForTimeout(400);
  check(await page.locator('.services-acc .acc-item.open').count()===1,'service opens '+width);
  await page.locator('.services-acc .acc-head').first().click();
  if(width<=900){
   check(await page.locator('.system-sticky').evaluate(e=>getComputedStyle(e).position)==='static','System not pinned '+width);
   check(await page.locator('.system-panel').evaluateAll(es=>es.every(e=>getComputedStyle(e).visibility==='visible'&&e.getBoundingClientRect().width<=innerWidth)),'all System panels visible '+width);
   check(await page.locator('.system-spacer').isHidden(),'no blank scroll spacer '+width);
  }
  if(width>=1240){
   await page.locator('.nav-links .nav-item').filter({has:page.locator('.dropdown')}).hover();
   await page.waitForTimeout(350);
   check(await page.locator('.dropdown-disciplines').evaluate(e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth;}),'desktop mega-menu stays within viewport '+width);
  }
  if(width<1240){
   await page.locator('#navBurger').click();
   check(await page.locator('#mobileMenu').getAttribute('aria-hidden')==='false','menu opens '+width);
   check(await page.locator('#mobileMenu .mobile-links>li').first().evaluate(e=>e.getBoundingClientRect().top>0),'menu first link reachable '+width);
   await page.waitForFunction(()=>document.activeElement.id==='mobileMenuClose');
   check(true,'menu receives keyboard focus '+width);
   await page.locator('.mobile-menu-head .brand').focus();
   await page.keyboard.press('Shift+Tab');
   check(await page.evaluate(()=>document.activeElement.getAttribute('href').startsWith('tel:')),'menu traps keyboard focus '+width);
   await page.locator('.mobile-services-disclosure summary').click();
   check(await page.locator('.mobile-service-links a').count()===11,'all services present '+width);
   await page.locator('.mobile-contact').scrollIntoViewIfNeeded();
   check(await page.locator('.mobile-menu-close').evaluate(e=>e.getBoundingClientRect().top>=0),'close remains reachable '+width);
   await page.locator('.mobile-services-disclosure summary').scrollIntoViewIfNeeded();
   if(width===390) await page.screenshot({path:path.join(screenshotDir,'menu-mobile.png')});
   await page.keyboard.press('Escape');
   check(await page.locator('#mobileMenu').getAttribute('aria-hidden')==='true','Escape closes menu '+width);
  }
  await page.evaluate(()=>window.scrollTo({top:1000,behavior:'instant'}));await page.waitForTimeout(150);
  check(await page.locator('#backToTop').isVisible(),'back to top visible '+width);
  await page.locator('#backToTop').click();await page.waitForTimeout(900);
  check(await page.evaluate(()=>scrollY<10),'back to top works '+width);
  check(await page.locator('#chatbotFab img').evaluate(e=>e.complete&&e.naturalWidth>0),'Alia photo loads '+width);
  if(width===390){await page.locator('.system').screenshot({path:path.join(screenshotDir,'system-mobile.png')});}
  if(width===1440){await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));await page.screenshot({path:path.join(screenshotDir,'home-desktop.png')});}
  check(errors.length===0,'no JS errors '+width+' '+errors.join(';'));
  for(const route of ['blog','resources','portfolio']){
    await page.goto(base+'/'+route+'/');await page.waitForTimeout(300);
    check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),route+' no overflow '+width);
    check(await page.locator('.blog-card,.resource-card,.pf-card').count()>=2,route+' sample cards '+width);
  }

 }
 console.log(checks+' browser checks passed');await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
