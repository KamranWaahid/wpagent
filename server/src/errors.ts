export class WPAgentError extends Error {
  readonly code:
    | "auth"
    | "capability"
    | "validation"
    | "network"
    | "not_found"
    | "safety"
    | "error";
  readonly status?: number;
  readonly details?: Record<string, unknown>;

  constructor(
    code: WPAgentError["code"],
    message: string,
    options: { status?: number; details?: Record<string, unknown>; cause?: unknown } = {},
  ) {
    super(message);
    this.name = "WPAgentError";
    this.code = code;
    this.status = options.status;
    this.details = options.details;
    if (options.cause !== undefined) {
      this.cause = options.cause;
    }
  }

  toJSON(): Record<string, unknown> {
    return {
      error: true,
      code: this.code,
      message: this.message,
      status: this.status,
      details: this.details ?? {},
    };
  }

  static fromUnknown(err: unknown): WPAgentError {
    if (err instanceof WPAgentError) {
      return err;
    }
    if (err instanceof Error) {
      return new WPAgentError("error", err.message, { cause: err });
    }
    return new WPAgentError("error", "Unknown error");
  }
}

export function isRetryableStatus(status: number): boolean {
  return status === 429 || status === 502 || status === 503 || status === 504;
}
