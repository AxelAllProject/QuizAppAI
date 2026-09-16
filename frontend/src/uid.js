let counter = 0

/**
 * Identifiant unique pour les clés React. `crypto.randomUUID` n'existe que dans un
 * contexte sécurisé (HTTPS ou localhost) : ouverte depuis une autre machine du réseau
 * (http://192.168.x.x:5173), la page planterait sans ce repli.
 */
export function uid() {
  return globalThis.crypto?.randomUUID?.() ?? `id-${Date.now().toString(36)}-${++counter}`
}
