/** Shared types and channel themes for the hero chat demo. */
export type ChannelKey = 'whatsapp' | 'telegram'

export type ChatMessage =
  | { id: number; kind: 'out'; text: string; time: string }
  | { id: number; kind: 'note'; text: string }
  | { id: number; kind: 'typing' }
  | { id: number; kind: 'summary'; title: string; lines: string[]; state: 'open' | 'generating' | 'sent'; time: string }
  | { id: number; kind: 'document'; fileName: string; meta: string; caption: string; time: string }

export const THEMES: Record<ChannelKey, {
  label: string; header: string; headerText: string; sub: string; wallpaper: string; out: string; tick: string; send: string; button: string
}> = {
  whatsapp: {
    label: 'WhatsApp',
    header: '#0f5d52',
    headerText: '#ffffff',
    sub: 'rgba(255,255,255,0.75)',
    wallpaper: 'radial-gradient(rgba(15,93,82,0.07) 1px, transparent 1.4px) 0 0 / 14px 14px, #ece5dc',
    out: '#d8f6cb',
    tick: '#2f8fd8',
    send: '#13866d',
    button: '#0f7a64',
  },
  telegram: {
    label: 'Telegram',
    header: '#4a7ea8',
    headerText: '#ffffff',
    sub: 'rgba(255,255,255,0.78)',
    wallpaper: 'radial-gradient(rgba(74,126,168,0.10) 1px, transparent 1.4px) 0 0 / 16px 16px, linear-gradient(160deg,#dfe9d3 0%,#cfdfc4 100%)',
    out: '#e3f8d0',
    tick: '#4aa35c',
    send: '#3b8fd1',
    button: '#3b7fb5',
  },
}

/** A message before it gets an id (Omit distributed over the union). */
export type NewChatMessage = ChatMessage extends infer M ? (M extends ChatMessage ? Omit<M, 'id'> : never) : never
