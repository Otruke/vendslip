/**
 * VendSlip imagery. Every photo is VendSlip's own brand photography; every slip is real output
 * from the VendSlip PDF engine. Each image ships in two widths for responsive `srcSet`s.
 */
import heroReceiptLandscape from './backgrounds/hero-receipt-landscape.svg'
import heroReceiptPortrait from './backgrounds/hero-receipt-portrait.svg'
import dashLedger900 from './images/dash-ledger-900.webp'
import dashLedger1600 from './images/dash-ledger-1600.webp'
import desk900 from './images/desk-900.webp'
import desk1600 from './images/desk-1600.webp'
import logo3d900 from './images/logo-3d-900.webp'
import qrProof400 from './images/qr-proof-400.webp'
import slipInvoice600 from './images/slip-invoice-600.webp'
import slipInvoice1000 from './images/slip-invoice-1000.webp'
import slipReceiptMinimal600 from './images/slip-receipt-minimal-600.webp'
import slipReceiptMinimal1000 from './images/slip-receipt-minimal-1000.webp'
import slipReceipt600 from './images/slip-receipt-600.webp'
import slipReceipt1000 from './images/slip-receipt-1000.webp'
import wallDark900 from './images/wall-dark-900.webp'
import wallDark1600 from './images/wall-dark-1600.webp'
import wallGlass900 from './images/wall-glass-900.webp'
import wallGlass1600 from './images/wall-glass-1600.webp'
import wallLight900 from './images/wall-light-900.webp'
import wallLight1600 from './images/wall-light-1600.webp'

export type ResponsiveImage = { src: string; srcSet: string; width: number; height: number }

const img = (small: string, smallW: number, large: string, largeW: number, ratio: number): ResponsiveImage => ({
  src: small,
  srcSet: `${small} ${smallW}w, ${large} ${largeW}w`,
  width: largeW,
  height: Math.round(largeW * ratio),
})

/** Hero art: VendSlip's own flat vector illustration (ledger paper + a giant receipt in the logo's shape). */
export const heroBackground = {
  landscape: heroReceiptLandscape,
  portrait: heroReceiptPortrait,
}

export const photos = {
  desk: img(desk900, 900, desk1600, 1600, 1178 / 1600),
  wallLight: img(wallLight900, 900, wallLight1600, 1600, 0.75),
  wallDark: img(wallDark900, 900, wallDark1600, 1600, 1190 / 2048),
  wallGlass: img(wallGlass900, 900, wallGlass1600, 1600, 0.75),
  logo3d: { src: logo3d900, srcSet: `${logo3d900} 900w`, width: 900, height: 900 } satisfies ResponsiveImage,
}

export const slips = {
  invoice: img(slipInvoice600, 600, slipInvoice1000, 1000, 932 / 1000),
  receipt: img(slipReceipt600, 600, slipReceipt1000, 1000, 0.8),
  receiptMinimal: img(slipReceiptMinimal600, 600, slipReceiptMinimal1000, 1000, 1362 / 1000),
}

/** Real VendSlip product captures (dashboard screenshot, QR proof cropped from a real PDF). */
export const product = {
  ledger: img(dashLedger900, 900, dashLedger1600, 1600, 0.625),
  qrProof: { src: qrProof400, srcSet: `${qrProof400} 390w`, width: 390, height: 250 } satisfies ResponsiveImage,
}
