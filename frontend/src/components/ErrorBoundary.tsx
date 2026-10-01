import { Component, type ReactNode } from 'react';

export class ErrorBoundary extends Component<{ children: ReactNode }, { error: string | null }> {
  state = { error: null as string | null };
  static getDerivedStateFromError(e: unknown) {
    return { error: e instanceof Error ? e.message : 'Unexpected error' };
  }
  componentDidCatch() {
    // Intentionally minimal: console only, backend 500s are surfaced via toast elsewhere.
    console.error(this.state.error);
  }
  render() {
    if (this.state.error) {
      return (
        <div className="card">
          <h3>Something broke</h3>
          <p className="muted">{this.state.error}</p>
          <button className="btn-ghost btn-sm" onClick={() => this.setState({ error: null })}>Retry</button>
        </div>
      );
    }
    return this.props.children;
  }
}
