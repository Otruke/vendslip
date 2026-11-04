import { Fragment, type CSSProperties } from 'react'

/**
 * Splits a heading into words so a data-reveal="type" parent can set them in one at a time,
 * like a line being typeset. Screen readers get the sentence once (the visual words are hidden).
 */
export function Words({ children }: { children: string }) {
  const words = children.split(/\s+/).filter(Boolean)
  return (
    <>
      <span className="sr-only">{children}</span>
      {words.map((w, i) => (
        <Fragment key={`${w}-${i}`}>
          <span className="vs-word" aria-hidden>
            <span style={{ '--w': i } as CSSProperties}>{w}</span>
          </span>
          {i < words.length - 1 ? ' ' : null}
        </Fragment>
      ))}
    </>
  )
}
