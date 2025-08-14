<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Air Drawing – MediaPipe Hands</title>
    <style>
      :root {
        --bg: #0f172a;
        --panel: #111827;
        --text: #e5e7eb;
        --muted: #9ca3af;
        --accent: #22c55e;
      }
      * {
        box-sizing: border-box;
      }
      body {
        margin: 0;
        background: linear-gradient(180deg, #0b1023, #0f172a);
        color: var(--text);
        font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu,
          Helvetica, Arial, sans-serif;
      }
      .wrap {
        max-width: 980px;
        margin: 24px auto;
        padding: 16px;
      }
      .title {
        display: flex;
        align-items: center;
        gap: 12px;
      }
      .title h1 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
      }
      .panel {
        margin-top: 12px;
        background: rgba(17, 24, 39, 0.8);
        backdrop-filter: saturate(160%) blur(6px);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 16px;
        padding: 12px;
      }
      .stage {
        position: relative;
        aspect-ratio: 16/9;
        max-height: 70vh;
        border-radius: 14px;
        overflow: hidden;
        background: #000;
      }
      video,
      canvas {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
      }
      video {
        transform: scaleX(-1);
        object-fit: cover;
        opacity: 0.22;
      }
      #overlay {
        pointer-events: none;
      }
      .toolbar {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
        margin-top: 10px;
      }
      .toolbar .group {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #0b1222;
        border: 1px solid rgba(255, 255, 255, 0.06);
        padding: 8px 10px;
        border-radius: 12px;
      }
      .btn {
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: #0d1b2a;
        color: #e5e7eb;
        padding: 8px 12px;
        border-radius: 12px;
        cursor: pointer;
      }
      .btn:hover {
        border-color: #4ade80;
      }
      .hint {
        color: var(--muted);
        font-size: 12px;
        margin-top: 8px;
      }
      .pill {
        display: inline-block;
        background: #0b1222;
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 2px 8px;
        border-radius: 999px;
        color: #a3e635;
        font-size: 12px;
      }
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="title">
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M12 19l7-7 3 3-7 7-3-1z" />
          <path d="M18 13l-1-1" />
          <path d="M2 22l6-6" />
          <path d="M3 21l-2-2 6-6 2 2-6 6z" />
        </svg>
        <h1>Air Drawing – MediaPipe Hands</h1>
        <span class="pill">Jepit jempol + telunjuk untuk menggambar</span>
      </div>

      <div class="panel">
        <div class="stage">
          <video id="video" playsinline muted></video>
          <canvas id="overlay"></canvas>
          <canvas id="paint"></canvas>
        </div>

        <div class="toolbar">
          <div class="group">
            <label>Warna</label>
            <input type="color" id="color" value="#00e887" />
          </div>
          <div class="group">
            <label>Tebal</label>
            <input type="range" id="thick" min="1" max="20" value="5" />
            <span id="thickVal">5px</span>
          </div>
          <button class="btn" id="clear">Bersihkan</button>
          <button class="btn" id="save">Simpan PNG</button>
          <span class="hint"
            >Tip: Gerakkan tangan di depan kamera. <b>Pinch</b> (jempol+telunjuk
            menyatu) = mulai/menggambar. Lepas pinch = berhenti.</span
          >
        </div>
      </div>

      <p class="hint">
        Catatan: Akses kamera butuh <b>HTTPS</b> atau <b>localhost</b>. Untuk
        lokal, jalankan server sederhana:
        <code>python -m http.server 8000</code> lalu buka
        <code>http://localhost:8000</code>.
      </p>
    </div>

    <!-- MediaPipe (classic) -->
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/hands/hands.js"></script>

    <script>
      const video = document.getElementById("video");
      const overlay = document.getElementById("overlay");
      const paint = document.getElementById("paint");
      const ctxOverlay = overlay.getContext("2d");
      const ctxPaint = paint.getContext("2d");

      const colorEl = document.getElementById("color");
      const thickEl = document.getElementById("thick");
      const thickVal = document.getElementById("thickVal");
      const btnClear = document.getElementById("clear");
      const btnSave = document.getElementById("save");

      let penColor = colorEl.value;
      let penWidth = +thickEl.value;
      thickVal.textContent = penWidth + "px";

      colorEl.oninput = () => (penColor = colorEl.value);
      thickEl.oninput = () => {
        penWidth = +thickEl.value;
        thickVal.textContent = penWidth + "px";
      };

      btnClear.onclick = () => {
        ctxPaint.clearRect(0, 0, paint.width, paint.height);
      };

      btnSave.onclick = () => {
        // gabungkan overlay landmarks & paint jika mau; default hanya gambar pengguna
        const tmp = document.createElement("canvas");
        tmp.width = paint.width;
        tmp.height = paint.height;
        const t = tmp.getContext("2d");
        t.fillStyle = "#000";
        t.fillRect(0, 0, tmp.width, tmp.height);
        t.drawImage(paint, 0, 0);
        const a = document.createElement("a");
        a.download = "air-drawing.png";
        a.href = tmp.toDataURL("image/png");
        a.click();
      };

      // Resize canvases to match stage size
      function fitCanvases() {
        const rect = video.parentElement.getBoundingClientRect();
        [overlay, paint].forEach((c) => {
          c.width = rect.width;
          c.height = rect.height;
        });
      }
      window.addEventListener("resize", fitCanvases);

      // Map normalized [0..1] to canvas coords with horizontal flip to match mirrored video
      function toCanvasXY(landmark) {
        const x = overlay.width - landmark.x * overlay.width;
        const y = landmark.y * overlay.height;
        return { x, y };
      }

      // Distance between two normalized landmarks
      function ndist(a, b) {
        const dx = a.x - b.x;
        const dy = a.y - b.y;
        const dz = (a.z || 0) - (b.z || 0);
        return Math.hypot(dx, dy, dz);
      }

      let lastPoint = null;
      let drawing = false;

      function onResults(results) {
        ctxOverlay.clearRect(0, 0, overlay.width, overlay.height);

        if (
          !results.multiHandLandmarks ||
          results.multiHandLandmarks.length === 0
        ) {
          lastPoint = null;
          drawing = false;
          return;
        }

        const landmarks = results.multiHandLandmarks[0];

        // Visualize landmarks minimally
        drawingUtils.drawConnectors(ctxOverlay, landmarks, HAND_CONNECTIONS, {
          lineWidth: 2,
        });
        drawingUtils.drawLandmarks(ctxOverlay, landmarks, { radius: 2 });

        const tipIndex = landmarks[8];
        const tipThumb = landmarks[4];
        const isPinching = ndist(tipIndex, tipThumb) < 0.045; // threshold empiris

        const p = toCanvasXY(tipIndex);

        if (isPinching) {
          // Mulai / lanjut menggambar
          if (!drawing) {
            drawing = true;
            lastPoint = p;
          }
          ctxPaint.strokeStyle = penColor;
          ctxPaint.lineWidth = penWidth;
          ctxPaint.lineCap = "round";
          ctxPaint.lineJoin = "round";
          ctxPaint.beginPath();
          ctxPaint.moveTo(lastPoint.x, lastPoint.y);
          ctxPaint.lineTo(p.x, p.y);
          ctxPaint.stroke();
          lastPoint = p;

          // indikator pinch
          ctxOverlay.beginPath();
          ctxOverlay.arc(p.x, p.y, 8, 0, Math.PI * 2);
          ctxOverlay.strokeStyle = "#22c55e";
          ctxOverlay.lineWidth = 3;
          ctxOverlay.stroke();
        } else {
          // Lepas menggambar
          drawing = false;
          lastPoint = null;
        }
      }

      async function init() {
        // getUserMedia needs https or localhost
        try {
          const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: "user" },
          });
          video.srcObject = stream;
          await video.play();
          fitCanvases();
        } catch (err) {
          alert(
            "Gagal akses kamera. Jalankan dari https/localhost. Detail: " +
              err.message
          );
          return;
        }

        const hands = new Hands({
          locateFile: (file) =>
            `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}`,
        });
        hands.setOptions({
          maxNumHands: 1,
          modelComplexity: 1,
          minDetectionConfidence: 0.6,
          minTrackingConfidence: 0.6,
        });
        hands.onResults(onResults);

        const camera = new Camera(video, {
          onFrame: async () => {
            await hands.send({ image: video });
          },
          width: 1280,
          height: 720,
        });
        camera.start();
      }

      // drawing_utils alias for brevity
      const drawingUtils = window; // drawing utils exposes drawConnectors/drawLandmarks globally

      init();
    </script>
  </body>
</html>
