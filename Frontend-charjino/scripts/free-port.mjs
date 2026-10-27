import { execSync } from 'node:child_process';

const port = process.env.PORT || '3001';

function listeningPids() {
  try {
    const out = execSync('netstat -ano', { encoding: 'utf8' });
    const pids = new Set();

    for (const line of out.split(/\r?\n/)) {
      if (!line.includes('LISTENING')) continue;
      if (!line.includes(`:${port} `) && !line.includes(`:${port}\t`)) continue;
      const pid = line.trim().split(/\s+/).pop();
      if (pid && pid !== '0') pids.add(pid);
    }

    return [...pids];
  } catch {
    return [];
  }
}

for (const pid of listeningPids()) {
  try {
    execSync(`taskkill /PID ${pid} /F`, { stdio: 'ignore' });
    console.log(`Port ${port} was busy; stopped pid ${pid}`);
  } catch {
    // already gone
  }
}
