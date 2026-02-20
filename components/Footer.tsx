export default function Footer() {
  const year = new Date().getFullYear()
  return (
    <footer className="footer">
      <p>&copy; {year} Invento </p>
      <p style={{ fontSize: '12px', opacity: 0.6, marginTop: '4px' }}>
        Construido pelo departamento de TI &middot; Yorozu &middot; BR
      </p>
    </footer>
  )
}
