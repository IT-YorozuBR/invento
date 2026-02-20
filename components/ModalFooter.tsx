export default function ModalFooter({ csrfToken }: { csrfToken: string }) {
  return (
    <>
      {/* Modal QR Code Scanner */}
      <div id="qrScannerModal" className="modal-overlay" style={{ display: 'none' }}>
        <div className="modal" style={{ maxWidth: '600px' }}>
          <div className="modal-header">
            <h3><i className="fas fa-qrcode"></i> Scanner de QR Code</h3>
          </div>
          <div className="modal-content">
            <p style={{ color: 'var(--gray)', marginBottom: '15px', textAlign: 'center' }}>
              Posicione o QR Code dentro da área marcada
            </p>
            <div id="qr-reader" style={{ width: '100%' }}></div>
            <div style={{ marginTop: '15px', padding: '12px', background: '#f0f8ff', borderRadius: 'var(--border-r)', fontSize: '13px', color: 'var(--secondary)' }}>
              <strong><i className="fas fa-info-circle"></i> Formato esperado:</strong><br />
              <code style={{ background: 'white', padding: '4px 8px', borderRadius: '4px', marginTop: '6px', display: 'inline-block' }}>DEP + PARTNUMBER</code>
              <br /><small style={{ color: 'var(--gray)', marginTop: '4px', display: 'block' }}>Exemplo: B9M555119496R → Depósito: <strong>B9M</strong> | Part Number: <strong>555119496R</strong></small>
            </div>
          </div>
          <div className="modal-actions">
            <button onClick={undefined} className="btn btn-outline">
              <i className="fas fa-times"></i> Cancelar
            </button>
          </div>
        </div>
      </div>

      {/* CSRF Token para uso no JS */}
      <script dangerouslySetInnerHTML={{ __html: `window.csrfToken = ${JSON.stringify(csrfToken)};` }} />
    </>
  )
}
