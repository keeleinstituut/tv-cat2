/**
 * The text of a segment is plain text with a few formatting tags:
 *
 *   <b> <i> <u> <s> <sup> <sub> <br>   and   &amp; &lt; &gt;   for the characters & < >
 *
 * This is what the backend stores and what catto-filters writes into files. The browser editor produces other
 * markup (strong, em, span, div, &nbsp;, ...), so everything the editor emits goes through normalizeSegmentText.
 * It mirrors App\Support\SegmentText in the backend, which applies the same rules again on every save.
 */

const TAGS: Record<string, string> = {
  b: 'b', strong: 'b',
  i: 'i', em: 'i',
  u: 'u', ins: 'u',
  s: 's', strike: 's', del: 's',
  sup: 'sup',
  sub: 'sub',
}

// Elements that start a new line (the editor creates them when Enter is pressed)
const BLOCKS = new Set(['div', 'p', 'li', 'tr'])

/** Escapes text typed by a person so it can be compared with, or put into, segment text. */
export const escapeText = (text: string): string =>
  text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

const parseBody = (html: string): HTMLElement =>
  // An inert document: nothing in it is executed or loaded
  new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html').body

/** The text a person reads: tags removed, <br> as a space, entities decoded. */
export const plainText = (html: string | null | undefined): string => {
  if (!html) return ''
  return parseBody(html.replace(/<br\s*\/?>/gi, ' ')).textContent ?? ''
}

const children = (node: Node): string => {
  let out = ''
  node.childNodes.forEach((child) => {
    if (child.nodeType === Node.TEXT_NODE) {
      out += escapeText(child.nodeValue ?? '')
      return
    }
    if (child.nodeType !== Node.ELEMENT_NODE) return

    const name = (child as Element).tagName.toLowerCase()
    if (name === 'br') {
      out += '<br>'
    } else if (TAGS[name]) {
      const inner = children(child)
      out += inner ? `<${TAGS[name]}>${inner}</${TAGS[name]}>` : ''
    } else if (BLOCKS.has(name)) {
      // A block is a line: text before it ends the previous line, and it ends with a line break itself.
      // <div><br></div> is how a browser writes an empty line.
      let inner = children(child)
      if (inner === '<br>') inner = ''
      if (out !== '' && !out.endsWith('<br>')) out += '<br>'
      out += inner + '<br>'
    } else if (name !== 'script' && name !== 'style') {
      out += children(child)
    }
  })
  return out
}

/** Brings markup from the editor (or the clipboard) into the segment text form. Applying it twice changes nothing. */
export const normalizeSegmentText = (html: string): string => {
  if (!/[<&>]/.test(html)) return html
  // Browsers leave a <br> at the end of the last line
  return children(parseBody(html)).replace(/(<br>)+$/, '')
}
