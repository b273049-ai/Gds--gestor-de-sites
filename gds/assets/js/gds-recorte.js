/* ============================================================
 * GDS — Tirar o fundo da foto do produto (grátis, no navegador do dono)
 * Usa o modelo U²-Net (licença Apache-2.0) hospedado no próprio GDS e o ONNX Runtime Web (MIT),
 * baixados só quando o dono toca em "Tirar o fundo". Nada sai do aparelho além do PNG final.
 * Uso: gdsRecortar(urlDaFoto, aoProgresso) → Promise<Blob WebP/PNG recortado e enquadrado>
 * ============================================================ */
(function () {
    'use strict';
    var ORT = 'https://cdn.jsdelivr.net/npm/onnxruntime-web@1.20.1/dist/';
    var MODELO = '/assets/modelos/u2net-recorte.onnx';
    var LADO = 320, MEDIA = [0.485, 0.456, 0.406], DESVIO = [0.229, 0.224, 0.225];
    var sessao = null;

    function carregarScript(src) {
        return new Promise(function (ok, erro) {
            if (window.ort) return ok();
            var s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = function () { erro(new Error('Sem internet para baixar a ferramenta de recorte.')); };
            document.head.appendChild(s);
        });
    }

    function abrirSessao(progresso) {
        if (sessao) return Promise.resolve(sessao);
        progresso && progresso('Baixando a ferramenta de recorte (só na primeira vez)…');
        return carregarScript(ORT + 'ort.min.js').then(function () {
            window.ort.env.wasm.wasmPaths = ORT;
            window.ort.env.wasm.numThreads = 1;
            return window.ort.InferenceSession.create(window.gdsRecorteModelo || MODELO, { executionProviders: ['wasm'] });
        }).then(function (s) { sessao = s; return s; });
    }

    function carregarImagem(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) throw new Error('Não consegui abrir a foto.');
            return r.blob();
        }).then(function (b) {
            return new Promise(function (ok, erro) {
                var img = new Image(); img.onload = function () { ok(img); }; img.onerror = function () { erro(new Error('Formato de foto não suportado.')); };
                img.src = URL.createObjectURL(b);
            });
        }).catch(function (e) { throw new Error(e && /Failed to fetch|NetworkError/i.test(e.message) ? 'Essa foto está em outro site e não pode ser recortada. Envie a foto do aparelho.' : e.message); });
    }

    // Foto → tensor 1×3×320×320 (como o U²-Net foi treinado)
    function tensorDe(img) {
        var c = document.createElement('canvas'); c.width = c.height = LADO;
        var g = c.getContext('2d'); g.drawImage(img, 0, 0, LADO, LADO);
        var px = g.getImageData(0, 0, LADO, LADO).data, n = LADO * LADO, max = 0;
        for (var i = 0; i < px.length; i++) if ((i & 3) !== 3 && px[i] > max) max = px[i];
        max = max || 255;
        var d = new Float32Array(3 * n);
        for (var p = 0; p < n; p++) for (var k = 0; k < 3; k++) d[k * n + p] = (px[p * 4 + k] / max - MEDIA[k]) / DESVIO[k];
        return new window.ort.Tensor('float32', d, [1, 3, LADO, LADO]);
    }

    window.gdsRecortar = function (url, progresso) {
        var img;
        return carregarImagem(url).then(function (i) { img = i; return abrirSessao(progresso); }).then(function (s) {
            progresso && progresso('Tirando o fundo…');
            var ent = {}; ent[s.inputNames[0]] = tensorDe(img);
            return s.run(ent);
        }).then(function (saida) {
            var m = saida[sessao.outputNames[0]].data, mn = Infinity, mx = -Infinity;
            for (var i = 0; i < m.length; i++) { if (m[i] < mn) mn = m[i]; if (m[i] > mx) mx = m[i]; }
            // máscara 320×320 → tamanho da foto (o navegador suaviza a borda ao ampliar)
            var mc = document.createElement('canvas'); mc.width = mc.height = LADO;
            var mg = mc.getContext('2d'), md = mg.createImageData(LADO, LADO);
            for (var p = 0; p < m.length; p++) { var a = Math.round((m[p] - mn) / ((mx - mn) || 1) * 255); md.data[p * 4 + 3] = a < 12 ? 0 : a > 240 ? 255 : a; }
            mg.putImageData(md, 0, 0);
            var W = img.naturalWidth, H = img.naturalHeight, esc = Math.min(1, 900 / Math.max(W, H));
            W = Math.round(W * esc); H = Math.round(H * esc);
            var c = document.createElement('canvas'); c.width = W; c.height = H;
            var g = c.getContext('2d');
            g.imageSmoothingQuality = 'high';
            g.drawImage(mc, 0, 0, W, H);
            g.globalCompositeOperation = 'source-in';
            g.drawImage(img, 0, 0, W, H);
            // enquadra no produto (corta o vazio em volta, com uma margem)
            var dd = g.getImageData(0, 0, W, H).data, x0 = W, y0 = H, x1 = -1, y1 = -1;
            for (var y = 0; y < H; y++) for (var x = 0; x < W; x++) if (dd[(y * W + x) * 4 + 3] > 40) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; }
            if (x1 < 0 || (x1 - x0) * (y1 - y0) < W * H * 0.02) throw new Error('Não achei o produto na foto. Use uma foto com o produto inteiro e no centro.');
            var mg2 = Math.round(Math.max(x1 - x0, y1 - y0) * 0.04);
            x0 = Math.max(0, x0 - mg2); y0 = Math.max(0, y0 - mg2); x1 = Math.min(W - 1, x1 + mg2); y1 = Math.min(H - 1, y1 + mg2);
            var f = document.createElement('canvas'); f.width = x1 - x0 + 1; f.height = y1 - y0 + 1;
            f.getContext('2d').drawImage(c, x0, y0, f.width, f.height, 0, 0, f.width, f.height);
            // WebP com transparência é bem mais leve; navegador que não gera WebP manda PNG
            return new Promise(function (ok) { f.toBlob(function (b) { if (b && b.type === 'image/webp') ok(b); else f.toBlob(ok, 'image/png'); }, 'image/webp', 0.86); });
        });
    };
})();
