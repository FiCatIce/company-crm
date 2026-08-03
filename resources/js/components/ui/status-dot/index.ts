import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as StatusDot } from "./StatusDot.vue"

/*
 * Status vocabulary indicator — reads only the locked --state-* tokens,
 * never the tenant-themeable accent.
 */
export const statusDotVariants = cva(
  "inline-block size-2 shrink-0 rounded-full",
  {
    variants: {
      state: {
        ok: "bg-state-ok",
        warn: "bg-state-warn",
        busy: "bg-state-busy",
        idle: "bg-state-idle",
      },
    },
    defaultVariants: {
      state: "idle",
    },
  },
)
export type StatusDotVariants = VariantProps<typeof statusDotVariants>
