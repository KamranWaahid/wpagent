export type EmailMessage = {
  to: string;
  subject: string;
  text: string;
};

export interface EmailSender {
  readonly provider: string;
  send(message: EmailMessage): Promise<void>;
}

export class ConsoleEmailSender implements EmailSender {
  readonly provider = "console";
  readonly sent: EmailMessage[] = [];

  async send(message: EmailMessage): Promise<void> {
    this.sent.push(message);
    process.stderr.write(
      JSON.stringify({
        ts: new Date().toISOString(),
        level: "info",
        msg: "auth_email_console",
        to: message.to,
        subject: message.subject,
      }) + "\n",
    );
  }
}

export class ResendEmailSender implements EmailSender {
  readonly provider = "resend";

  constructor(
    private readonly apiKey: string,
    private readonly from: string,
    private readonly fetchImpl: typeof fetch = fetch,
  ) {}

  async send(message: EmailMessage): Promise<void> {
    const response = await this.fetchImpl("https://api.resend.com/emails", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${this.apiKey}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        from: this.from,
        to: [message.to],
        subject: message.subject,
        text: message.text,
      }),
    });
    if (!response.ok) {
      throw new Error(`Resend email failed (${response.status})`);
    }
  }
}

export class PostmarkEmailSender implements EmailSender {
  readonly provider = "postmark";

  constructor(
    private readonly token: string,
    private readonly from: string,
    private readonly fetchImpl: typeof fetch = fetch,
  ) {}

  async send(message: EmailMessage): Promise<void> {
    const response = await this.fetchImpl("https://api.postmarkapp.com/email", {
      method: "POST",
      headers: {
        "X-Postmark-Server-Token": this.token,
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        From: this.from,
        To: message.to,
        Subject: message.subject,
        TextBody: message.text,
      }),
    });
    if (!response.ok) {
      throw new Error(`Postmark email failed (${response.status})`);
    }
  }
}

export function createEmailSender(options: {
  provider: "console" | "resend" | "postmark";
  from: string;
  resendApiKey?: string;
  postmarkToken?: string;
  nodeEnv?: string;
}): EmailSender {
  if (options.provider === "resend" && !options.resendApiKey && options.nodeEnv !== "production") {
    return new ConsoleEmailSender();
  }
  if (options.provider === "resend") {
    return new ResendEmailSender(options.resendApiKey ?? "", options.from);
  }
  if (options.provider === "postmark") {
    return new PostmarkEmailSender(options.postmarkToken ?? "", options.from);
  }
  return new ConsoleEmailSender();
}
