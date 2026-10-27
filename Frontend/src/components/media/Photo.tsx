import type { ImgHTMLAttributes } from 'react'
import type { ResponsiveImage } from '@/assets'
import { cn } from '@/lib/cn'

/** Responsive, lazy image with intrinsic size (no layout shift). */
export function Photo({ image, alt, sizes = '(min-width: 1024px) 50vw, 100vw', className, eager = false, ...rest }: {
  image: ResponsiveImage
  alt: string
  sizes?: string
  eager?: boolean
} & Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'srcSet' | 'width' | 'height' | 'alt'>) {
  return (
    <img
      src={image.src}
      srcSet={image.srcSet}
      sizes={sizes}
      width={image.width}
      height={image.height}
      alt={alt}
      loading={eager ? 'eager' : 'lazy'}
      decoding="async"
      className={cn('block h-auto w-full', className)}
      {...rest}
    />
  )
}
