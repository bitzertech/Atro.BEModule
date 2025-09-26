Espo.define('atro-bemodule:views/fields/envelope-polygon', 'views/fields/base', function (Dep) {
  const SVG_NS = 'http://www.w3.org/2000/svg';

  // layout & padding (px)
  const PAD_FRAME  = 10;
  const PAD_LEFT   = 58;   // plads til Y-akse + titel
  const PAD_RIGHT  = 16;
  const PAD_TOP    = 18;
  const PAD_BOTTOM = 40;   // plads til X-akse + titel

  // ticks & geometri
  const TARGET_TICKS = 8;
  const TICK_SIZE    = 5;   // længde på tick-streger
  const X_TICK_GAP   = 8;   // afstand fra akse til tick-labels
  const X_TITLE_GAP  = 26;  // afstand fra akse til akse-titel

  // data-margin før equalize
  const MARGIN_FRAC  = 0.10; // 10%

  function niceStep(range, target=TARGET_TICKS){
    if (!isFinite(range) || range <= 0) return 1;
    const rough = range / target;
    const pow = Math.pow(10, Math.floor(Math.log10(rough)));
    const f = rough / pow;
    const nf = f < 1.5 ? 1 : f < 3 ? 2 : f < 7 ? 5 : 10;
    return nf * pow;
  }
  function convexHull(points){
    if (points.length <= 3) return points.slice();
    const pts = points.slice().sort((a,b)=>a.x===b.x? a.y-b.y : a.x-b.x);
    const cross=(o,a,b)=>(a.x-o.x)*(b.y-o.y)-(a.y-o.y)*(b.x-o.x);
    const lower=[]; for(const p of pts){ while(lower.length>=2 && cross(lower.at(-2),lower.at(-1),p)<=0) lower.pop(); lower.push(p); }
    const upper=[]; for(let i=pts.length-1;i>=0;i--){ const p=pts[i]; while(upper.length>=2 && cross(upper.at(-2),upper.at(-1),p)<=0) upper.pop(); upper.push(p); }
    upper.pop(); lower.pop(); return lower.concat(upper);
  }
  function make(name, attrs) {
    const el = document.createElementNS(SVG_NS, name);
    for (const k in attrs) el.setAttribute(k, attrs[k]);
    return el;
  }
  function debounce(fn, ms){
    let t=null; return function(){ clearTimeout(t); t=setTimeout(()=>fn.apply(this, arguments), ms); };
  }

  return Dep.extend({
    isReadOnly: true,
    inlineEditDisabled: true,

    setup: function () {
      Dep.prototype.setup.call(this);
      for (let i = 1; i <= 9; i++) {
        this.listenTo(this.model, 'change:t0_' + i, this.reRender);
        this.listenTo(this.model, 'change:tc_' + i, this.reRender);
      }
      this.listenTo(this.model, 'change:envelope_type', this.reRender);

      // re-render ved resize
      this._onResize = debounce(() => this.reRender(), 120);
      window.addEventListener('resize', this._onResize);
      this.once('remove', () => window.removeEventListener('resize', this._onResize));
    },

    reRender: function () {
      this.once('after:render', this.draw, this);
      this.render();
    },

    afterRender: function () {
      this.$el.html(
        '<div class="envelope-polygon" style="width:100%;max-width:100%;overflow:hidden;box-sizing:border-box;">' +
          '<svg class="env-svg" style="display:block;width:100%;height:260px;" preserveAspectRatio="none"></svg>' +
        '</div>'
      );
      this.draw();
    },

    draw: function () {
      const $svg = this.$('svg.env-svg');
      if (!$svg.length) return;
      const svg = $svg.get(0);

      // responsiv størrelse
      const VBW = Math.max(1, this.$el.innerWidth() || svg.clientWidth || 800);
      const VBH = svg.clientHeight || 260;
      svg.setAttribute('viewBox', `0 0 ${VBW} ${VBH}`);

      while (svg.firstChild) svg.removeChild(svg.firstChild);

      // Hent punkter
      const ptsRaw = [];
      for (let i=1;i<=9;i++){
        const x=this.model.get('t0_'+i), y=this.model.get('tc_'+i);
        if (x!==null && x!=='' && y!==null && y!==''){
          const nx=Number(x), ny=Number(y);
          if (!Number.isNaN(nx) && !Number.isNaN(ny)) ptsRaw.push({x:nx,y:ny});
        }
      }
      if (!ptsRaw.length){
        this.$el.append('<div class="text-muted" style="margin-top:6px">No points</div>');
        return;
      }

      // Enheder/titler ud fra envelope_type
      const envType = this.model.get('envelope_type') || 'temperature';
      const unit = envType === 'pressure' ? 'bar' : (envType === 'temperature' ? '°C' : '');
      const xTitleText = unit ? `T0 (${unit})` : 'T0';
      const yTitleText = unit ? `TC (${unit})` : 'TC';

      // Hull + ekstremer
      const hull = convexHull(ptsRaw);
      let xs = hull.map(p=>p.x), ys = hull.map(p=>p.y);
      let minX=Math.min.apply(null,xs), maxX=Math.max.apply(null,xs);
      let minY=Math.min.apply(null,ys), maxY=Math.max.apply(null,ys);
      if (minX===maxX){ minX-=1; maxX+=1; }
      if (minY===maxY){ minY-=1; maxY+=1; }

      // data-margin før equalize
      const mx=(maxX-minX)*MARGIN_FRAC, my=(maxY-minY)*MARGIN_FRAC;
      minX-=mx; maxX+=mx; minY-=my; maxY+=my;

      // indre tegnefelt
      const innerLeft   = PAD_LEFT + PAD_FRAME;
      const innerRight  = VBW - (PAD_RIGHT + PAD_FRAME);
      const innerTop    = PAD_TOP + PAD_FRAME;
      const innerBottom = VBH - (PAD_BOTTOM + PAD_FRAME);
      const W = Math.max(1, innerRight - innerLeft);
      const H = Math.max(1, innerBottom - innerTop);

      // Ens skala
      const rx=maxX-minX, ry=maxY-minY;
      const S = Math.min(W/rx, H/ry);
      const needRx = W/S, needRy = H/S;
      const cx=(minX+maxX)/2, cy=(minY+maxY)/2;
      minX = cx - needRx/2; maxX = cx + needRx/2;
      minY = cy - needRy/2; maxY = cy + needRy/2;

      const sx = x => innerLeft + (x - minX) * S;
      const sy = y => innerBottom - (y - minY) * S;

      // Akser
      svg.appendChild(make('line',{x1:innerLeft,y1:innerBottom,x2:innerRight,y2:innerBottom,stroke:'#aaa'}));
      svg.appendChild(make('line',{x1:innerLeft,y1:innerTop,   x2:innerLeft,  y2:innerBottom,stroke:'#aaa'}));

      // ticks + labels
      const stepX = niceStep(maxX-minX), stepY = niceStep(maxY-minY);

      // X ticks & labels: PLACERET RELATIVT TIL AKSEN
      for (let x=Math.ceil(minX/stepX)*stepX; x<=maxX+1e-9; x+=stepX){
        const X=sx(x);
        svg.appendChild(make('line',{x1:X,y1:innerBottom,x2:X,y2:innerBottom+TICK_SIZE,stroke:'#888'}));
        const t=make('text',{
          x:X,
          y:innerBottom + TICK_SIZE + X_TICK_GAP,
          'text-anchor':'middle',
          'font-size':'11',
          fill:'#555'
        });
        t.textContent=(+x.toFixed(2));
        svg.appendChild(t);
      }

      // Y ticks & labels (uændret)
      for (let y=Math.ceil(minY/stepY)*stepY; y<=maxY+1e-9; y+=stepY){
        const Y=sy(y);
        svg.appendChild(make('line',{x1:innerLeft-TICK_SIZE,y1:Y,x2:innerLeft,y2:Y,stroke:'#888'}));
        const t=make('text',{x:innerLeft-(TICK_SIZE+3), y:Y+4, 'text-anchor':'end','font-size':'11',fill:'#555'});
        t.textContent=(+y.toFixed(2));
        svg.appendChild(t);
      }

      // Akse-TITLER (også relativt til aksen)
      const xTitle = make('text',{
        x:(innerLeft+innerRight)/2,
        y:innerBottom + X_TITLE_GAP,
        'text-anchor':'middle','font-size':'12', fill:'#333'
      });
      xTitle.textContent=xTitleText; svg.appendChild(xTitle);

      const yTitle = make('text',{
        x:12, y:(innerTop+innerBottom)/2,
        'text-anchor':'middle','font-size':'12', fill:'#333',
        transform:`rotate(-90 12 ${(innerTop+innerBottom)/2})`
      });
      yTitle.textContent=yTitleText; svg.appendChild(yTitle);

      // Polygon + punkter
      const pointsStr = hull.map(p=>`${sx(p.x)},${sy(p.y)}`).join(' ');
      svg.appendChild(make('polygon', { points: pointsStr, fill:'rgba(11,108,255,0.15)', stroke:'#0b6cff', 'stroke-width':'2' }));
      hull.forEach(p => svg.appendChild(make('circle',{cx:sx(p.x), cy:sy(p.y), r:3, fill:'#0b6cff'})));
    }
  });
});
