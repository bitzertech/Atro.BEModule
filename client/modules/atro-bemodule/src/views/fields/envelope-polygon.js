Espo.define('atro-bemodule:views/fields/envelope-polygon', 'views/fields/base', function (Dep) {
  return Dep.extend({
    type: 'base',
    template: '<div class="envelope-polygon"><svg width="100%" height="240" preserveAspectRatio="none"></svg></div>',

    setup: function () {
      // re-render når nogle af punkterne ændres (edit mode)
      for (var i = 1; i <= 9; i++) {
        this.listenTo(this.model, 'change:t0_' + i, this.render);
        this.listenTo(this.model, 'change:tc_' + i, this.render);
      }
    },

    afterRender: function () {
      var svg = this.$('svg');
      if (!svg.length) return;
      svg.empty();

      // Hent og filtrér punkter
      var pairs = [];
      for (var i = 1; i <= 9; i++) {
        var x = this.model.get('t0_' + i);
        var y = this.model.get('tc_' + i);
        if (x != null && y != null && x !== '' && y !== '') {
          pairs.push({ x: +x, y: +y });
        }
      }
      if (!pairs.length) {
        this.$el.html('<div class="text-muted">No points</div>');
        return;
      }

      // Sortér på x (T0) så polygonen tegnes i rækkefølge
      pairs.sort(function (a, b) { return a.x - b.x; });

      // Skaleringsområde
      var xs = pairs.map(p => p.x), ys = pairs.map(p => p.y);
      var minX = Math.min.apply(null, xs), maxX = Math.max.apply(null, xs);
      var minY = Math.min.apply(null, ys), maxY = Math.max.apply(null, ys);
      if (minX === maxX) { minX -= 1; maxX += 1; }
      if (minY === maxY) { minY -= 1; maxY += 1; }

      var w = svg[0].clientWidth || 600;
      var h = svg[0].clientHeight || 240;
      var pad = 12;

      function sx (x) { return pad + (x - minX) / (maxX - minX) * (w - 2 * pad); }
      function sy (y) { return (h - pad) - (y - minY) / (maxY - minY) * (h - 2 * pad); }

      // Akser
      svg.append('<line x1="'+pad+'" y1="'+(h-pad)+'" x2="'+(w-pad)+'" y2="'+(h-pad)+'" stroke="#999"/>');
      svg.append('<line x1="'+pad+'" y1="'+pad+'" x2="'+pad+'" y2="'+(h-pad)+'" stroke="#999"/>');

      // Polygon (lukket)
      var pts = pairs.map(p => sx(p.x) + ',' + sy(p.y)).join(' ');
      pts += ' ' + pts.split(' ')[0]; // luk tilbage til første punkt

      svg.append('<polygon points="'+pts+'" fill="rgba(0,123,255,0.15)" stroke="#007bff" stroke-width="2"/>');

      // Punkter
      pairs.forEach(function (p) {
        svg.append('<circle cx="'+sx(p.x)+'" cy="'+sy(p.y)+'" r="3" fill="#007bff"/>');
      });
    }
  });
});
