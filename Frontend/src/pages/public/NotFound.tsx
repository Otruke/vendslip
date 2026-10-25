import { Button, Container, Heading, Logo, Text } from '@/components/ui'

export default function NotFound() {
  return (
    <div className="flex min-h-dvh flex-col">
      <Container className="py-6"><Logo /></Container>
      <Container size="sm" className="flex flex-1 flex-col items-center justify-center pb-24 text-center">
        <p className="tabular text-6xl font-black text-netslip-tint">404</p>
        <Heading level={1} className="mt-2">This page slipped away.</Heading>
        <Text variant="lead" className="mt-3">The link may be old or mistyped.</Text>
        <div className="mt-8 flex gap-3"><Button to="/">Go home</Button><Button to="/verify" variant="outline">Verify a slip</Button></div>
      </Container>
    </div>
  )
}
