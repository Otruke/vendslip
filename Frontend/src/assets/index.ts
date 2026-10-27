/**
 * NetSlip imagery. Every photo is NetSlip's own brand photography; every slip is real output
 * from the NetSlip PDF engine. Each image ships in two widths for responsive `srcSet`s.
 */
import heroLandscape1280 from './backgrounds/hero-landscape-1280.webp'
import heroLandscape1920 from './backgrounds/hero-landscape-1920.webp'
import heroPortrait720 from './backgrounds/hero-portrait-720.webp'
import heroPortrait1080 from './backgrounds/hero-portrait-1080.webp'
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

export const heroBackground = {
  landscape: { src: heroLandscape1280, srcSet: `${heroLandscape1280} 1280w, ${heroLandscape1920} 1672w` },
  portrait: { src: heroPortrait720, srcSet: `${heroPortrait720} 720w, ${heroPortrait1080} 1080w` },
}

export const photos = {
  desk: img(desk900, 900, desk1600, 1600, 1178 / 1600),
  wallLight: img(wallLight900, 900, wallLight1600, 1600, 0.75),
  wallDark: img(wallDark900, 900, wallDark1600, 1600, 1178 / 1600),
  wallGlass: img(wallGlass900, 900, wallGlass1600, 1600, 0.75),
  logo3d: { src: logo3d900, srcSet: `${logo3d900} 900w`, width: 900, height: 900 } satisfies ResponsiveImage,
}

export const slips = {
  invoice: img(slipInvoice600, 600, slipInvoice1000, 1000, 932 / 1000),
  receipt: img(slipReceipt600, 600, slipReceipt1000, 1000, 0.8),
  receiptMinimal: img(slipReceiptMinimal600, 600, slipReceiptMinimal1000, 1000, 1362 / 1000),
}

/** Real NetSlip product captures (dashboard screenshot, QR proof cropped from a real PDF). */
export const product = {
  ledger: img(dashLedger900, 900, dashLedger1600, 1600, 0.625),
  qrProof: { src: qrProof400, srcSet: `${qrProof400} 390w`, width: 390, height: 250 } satisfies ResponsiveImage,
}
