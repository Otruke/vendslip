import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { ApiError } from './api'

/** Map Laravel 422 field errors onto react-hook-form; returns a general message if any remain. */
export function applyServerErrors<T extends FieldValues>(error: unknown, setError: UseFormSetError<T>, fields: readonly string[]): string | null {
  if (error instanceof ApiError && error.status === 422 && Object.keys(error.fieldErrors).length > 0) {
    let unmapped: string | null = null
    for (const [field, messages] of Object.entries(error.fieldErrors)) {
      const root = field.split('.')[0] ?? field
      if (fields.includes(root)) setError(root as Path<T>, { type: 'server', message: messages[0] })
      else unmapped ??= messages[0] ?? null
    }
    return unmapped
  }
  return error instanceof Error ? error.message : 'Something went wrong.'
}
