/**
 * Musique de fond des quiz, synthétisée avec la Web Audio API : aucun fichier
 * à charger, aucun droit à gérer. Une boucle de 4 mesures (Do – La m – Fa – Sol)
 * avec basse, arpège, grosse caisse et charleston, jouée à volume discret.
 */

/** Réglages de la musique : tempo, durée d'un pas, volume et avance de planification. */
const BPM = 112
const STEP = 60 / BPM / 4 // une double-croche
const VOLUME = 0.12
const LOOKAHEAD = 0.12 // secondes planifiées d'avance à chaque passage

// [basse, notes de l'arpège] en numéros MIDI, une entrée par mesure.
const CHORDS = [
  [48, [60, 64, 67, 72]],
  [45, [57, 60, 64, 69]],
  [41, [53, 57, 60, 65]],
  [43, [55, 59, 62, 67]],
]
/** Ordre des notes de l'accord jouées à chaque temps. */
const ARPEGGIO = [0, 1, 2, 3, 2, 3, 1, 2]

let ctx = null
let master = null
let noise = null
let timer = null
let stopTimeout = null
let step = 0
let nextTime = 0

/** Convertit un numéro de note MIDI en fréquence (Hz). */
const frequency = (midi) => 440 * 2 ** ((midi - 69) / 12)

/** Crée le contexte audio, le filtre, le volume général et le bruit des charleys. */
function setup() {
  ctx = new AudioContext()

  const lowpass = ctx.createBiquadFilter()
  lowpass.type = 'lowpass'
  lowpass.frequency.value = 2600
  master = ctx.createGain()
  master.gain.value = 0
  master.connect(lowpass).connect(ctx.destination)

  noise = ctx.createBuffer(1, ctx.sampleRate * 0.05, ctx.sampleRate)
  const data = noise.getChannelData(0)
  for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1
}

/** Joue une note avec une attaque rapide et une extinction progressive. */
function tone(midi, type, time, duration, volume) {
  const osc = ctx.createOscillator()
  const gain = ctx.createGain()
  osc.type = type
  osc.frequency.value = frequency(midi)
  gain.gain.setValueAtTime(0, time)
  gain.gain.linearRampToValueAtTime(volume, time + 0.01)
  gain.gain.exponentialRampToValueAtTime(0.001, time + duration)
  osc.connect(gain).connect(master)
  osc.start(time)
  osc.stop(time + duration + 0.02)
}

/** Joue un coup de grosse caisse. */
function kick(time) {
  const osc = ctx.createOscillator()
  const gain = ctx.createGain()
  osc.frequency.setValueAtTime(120, time)
  osc.frequency.exponentialRampToValueAtTime(45, time + 0.12)
  gain.gain.setValueAtTime(0.6, time)
  gain.gain.exponentialRampToValueAtTime(0.001, time + 0.15)
  osc.connect(gain).connect(master)
  osc.start(time)
  osc.stop(time + 0.17)
}

/** Joue un coup de charley (bruit filtré). */
function hat(time) {
  const source = ctx.createBufferSource()
  const filter = ctx.createBiquadFilter()
  const gain = ctx.createGain()
  source.buffer = noise
  filter.type = 'highpass'
  filter.frequency.value = 7000
  gain.gain.setValueAtTime(0.08, time)
  gain.gain.exponentialRampToValueAtTime(0.001, time + 0.04)
  source.connect(filter).connect(gain).connect(master)
  source.start(time)
}

/** Joue ce qui tombe sur un pas de la boucle : basse, arpège et percussions. */
function playStep(index, time) {
  const [bass, notes] = CHORDS[Math.floor(index / 16) % CHORDS.length]
  const position = index % 16

  if (position % 8 === 0 || position === 11) tone(bass, 'triangle', time, STEP * 3, 0.5)
  if (position % 2 === 0) tone(notes[ARPEGGIO[position / 2]], 'square', time, STEP * 0.9, 0.12)
  if (position === 0 || position === 8) kick(time)
  if (position % 4 === 2) hat(time)
}

/** Planifie les pas à venir un peu en avance, pour un tempo régulier. */
function schedule() {
  while (nextTime < ctx.currentTime + LOOKAHEAD) {
    playStep(step, nextTime)
    nextTime += STEP
    step = (step + 1) % (16 * CHORDS.length)
  }
}

/** Lance la musique de fond en fondu. */
export function startMusic() {
  if (!ctx) setup()
  clearTimeout(stopTimeout)

  // Sans geste récent de l'utilisateur, le navigateur garde le son suspendu : on réessaie au premier clic.
  if (ctx.state === 'suspended') {
    ctx.resume().catch(() => {})
    window.addEventListener('pointerdown', () => ctx.resume(), { once: true })
  }

  master.gain.cancelScheduledValues(ctx.currentTime)
  master.gain.setValueAtTime(master.gain.value, ctx.currentTime)
  master.gain.linearRampToValueAtTime(VOLUME, ctx.currentTime + 0.6)

  if (timer) return
  step = 0
  nextTime = ctx.currentTime + 0.05
  timer = setInterval(schedule, 25)
}

/** Arrête la musique en fondu. */
export function stopMusic() {
  if (!timer) return

  master.gain.cancelScheduledValues(ctx.currentTime)
  master.gain.setValueAtTime(master.gain.value, ctx.currentTime)
  master.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.4)

  clearTimeout(stopTimeout)
  stopTimeout = setTimeout(() => {
    clearInterval(timer)
    timer = null
  }, 450)
}
