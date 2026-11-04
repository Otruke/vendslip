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
    wallpaper: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14'%3E%3Ccircle cx='7.0' cy='7.0' r='1' fill='%23c9c0b3'/%3E%3C/svg%3E") 0 0 / 14px 14px, #ece5dc`,
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
    wallpaper: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16'%3E%3Ccircle cx='8.0' cy='8.0' r='1' fill='%23b7c9ab'/%3E%3C/svg%3E") 0 0 / 16px 16px, #d8e5cc`,
    out: '#e3f8d0',
    tick: '#4aa35c',
    send: '#3b8fd1',
    button: '#3b7fb5',
  },
}

/** A message before it gets an id (Omit distributed over the union). */
export type NewChatMessage = ChatMessage extends infer M ? (M extends ChatMessage ? Omit<M, 'id'> : never) : never
