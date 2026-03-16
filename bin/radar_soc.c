/*
 *  Watch the serial port for speed readings then
 *  share them to any one who had connected
 */

#include<stdio.h>
#include <termios.h>
#include <sys/ioctl.h>
#include <errno.h>
#include <stdlib.h>
#include <unistd.h>
#include <stdint.h>
#include <string.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <signal.h>
#include <poll.h>
#include <time.h>
#include <arpa/inet.h>
#define _GNU_SOURCE
#include <sys/socket.h>
#include <yaml.h>


int	run_gap = 1000;
int	port = 251;

struct time_struct_s
{
	int	max_speed;
	int	cnr_speed;
	int	curr_speed;
	int	time;
	int	cnt;
};

struct cmd1_s
{
	char	cf[2];
	uint8_t	num;
	uint8_t	min_speed;
	uint8_t	angle;
	uint8_t	sensitivity;
	char	nl[2];
} cmd1 = {
	{ 'C', 'F' }, 1, 5, 0, 5, { '\r', '\n' }
};

struct cmd2_s
{
	char	cf[2];
	uint8_t	num;
	uint8_t	dir;
	uint8_t	rate;
	uint8_t	units;
	char	nl[2];
} cmd2 = {
	{ 'C', 'F' }, 2, 1, 0, 0, { '\r', '\n' }
};

char conf_q[] = "CF\07\0\0\0\0\0\0\0\0\0\0\r\n";

char	*serial_name = "/dev/ttyS0";

void do_perror(char *mesg)
{
        perror(mesg);
        exit(1);
}

#ifndef CLOCK_MONOTONIC_COARSE
# define 	CLOCK_MONOTONIC_COARSE	CLOCK_MONOTONIC
#endif

time_t base_time = 0;

void time_h_setbase(void *ignore)
{
	struct timespec now;

	clock_gettime(CLOCK_MONOTONIC_COARSE, &now);

	base_time = now.tv_sec;
}

time_t time_h(void *ignore)
{
	struct timespec	now;

	clock_gettime(CLOCK_MONOTONIC_COARSE, &now);

	return (now.tv_sec - base_time) * 100 + now.tv_nsec / 10000000;
}

int set_radar_serial( char *dev_name )
{
        int	fd;

	speed_t	baud_rate=9600;
	struct	termios	t;

        //fd=open(dev_name, O_RDWR|O_NOCTTY|O_NONBLOCK|O_EXCL);
        fd=open(dev_name, O_RDWR|O_NOCTTY|O_EXCL);
	if (fd==-1) do_perror("Opening radar serial device");

        if (tcgetattr(fd, &t)) do_perror("tcgetattr");

	cfmakeraw(&t);
	t.c_cflag |= CLOCAL;
	t.c_cc[VMIN] = 9;
	t.c_cc[VTIME] = 1;
        cfsetspeed(&t, baud_rate);

	if (tcsetattr(fd, TCSANOW, &t )) perror("tcsetattr");

	return fd;
}

int	verbose=2;

int getNumber(const char *key, const unsigned char *token, const int min, const int max, const int deflt)
{
    int         val;
    char        *eos;

    val = strtol((char *)token, &eos, 10);

    if ((*eos != '\0') && (*eos != ' ')) {
        fprintf(stderr, "Bad value for %s: %s\n", key, token);
        return deflt;
    }
    if ((val < min) || (val > max)) {
        fprintf(stderr, "Value must be between %d and %d for %s: %s\n", min, max, key, token);
        return deflt;
    }

    return val;
}

void readConf(char* filename) {
    FILE* fh = fopen(filename, "r");
    yaml_parser_t parser;
    yaml_token_t token;

    if (!yaml_parser_initialize(&parser))
        fputs("Failed to initialize parser!\n", stderr);
    if (fh == NULL)
        fputs("Failed to open file!\n", stderr);
    yaml_parser_set_input_file(&parser, fh);


    /*  state = 0 = expect key
     *  state = 1 = expect value
     */
    int	  state = 0;
    char  *tk = NULL;

    do {
        yaml_parser_scan(&parser, &token);
        switch(token.type) {
            case YAML_KEY_TOKEN:     state = 0; break;
            case YAML_VALUE_TOKEN:   state = 1; break;
            case YAML_SCALAR_TOKEN:
                if (state == 1) {
                    if (!strcmp(tk, "title")) {
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "comment")) {
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "run_gap")) {
                        run_gap = getNumber(tk, token.data.scalar.value, 1000, 1000000, 1000);
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "min_speed")) {
                        cmd1.min_speed = getNumber(tk, token.data.scalar.value, 1, 100, 5);
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "angle")) {
                        cmd1.angle = getNumber(tk, token.data.scalar.value, 0, 90, 0);
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "sensitivity")) {
                        cmd1.sensitivity = getNumber(tk, token.data.scalar.value, 0, 100, 5);
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "direction")) {
                        cmd2.dir = getNumber(tk, token.data.scalar.value, 0, 2, 1);
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "rate")) {
                        cmd2.rate = getNumber(tk, token.data.scalar.value, 0, 20, 0);
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "units")) {
                        cmd2.units = getNumber(tk, token.data.scalar.value, 0, 1, 0);
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "port")) {
                        port = getNumber(tk, token.data.scalar.value, 1, 65535, 251);
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "debug")) {
                        verbose = getNumber(tk, token.data.scalar.value, 0, 10, 1);
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "device")) {
                        serial_name = strdup((char *)token.data.scalar.value);
                        free(tk); tk=NULL;
                    } else if (!strcmp(tk, "save_ver")) {
                        free(tk); tk=NULL;
                    } else {
                        printf("Unrecognised key: %s: %s\n", tk, token.data.scalar.value);
                    }
                } else if (state == 0) {
                    if (tk != NULL) free(tk);
                      tk = strdup((char *)token.data.scalar.value);
                }
                break;
           default: break;
           }
       if (token.type != YAML_STREAM_END_TOKEN)
           yaml_token_delete(&token);
   } while (token.type != YAML_STREAM_END_TOKEN);

   yaml_token_delete(&token);
   yaml_parser_delete(&parser);
   fclose(fh);
}

int get_speed(int radar_fd)
{
	static char	buff[512];
	static char	*buf_ptr = buff;
	char		*buf_end;
	int	speed = -2;


	int len = read(radar_fd, buf_ptr, sizeof(buff)-1-(buf_ptr-buff));
	buf_end = buf_ptr + len;
	*buf_end = '\0';

	if (verbose>4) {
	  printf("Radar IN(%ld:%d): <<%s>>\n", (buf_end - buff), len, buff);
	}
	char	*find_V = buf_end; 
	char	*partial_V = NULL;
	while (find_V-- >= buff) {
	  if (*find_V == 'V') {
	    if ((buf_end - find_V) < 7) {
	      partial_V = find_V;
	    }
	    else {
	      break;
	    }
	  }
	}
	if (*find_V == 'V') {
	  char	*end;
	  speed = strtol(find_V + 2, &end, 10) * 10;
	  if (end == find_V) {  // No numbers
	    return -1;
	  }
	  if (*end == '.') {
	    int  dec = strtol(end + 1, &end, 10);
	    if ((dec >= 0) && (dec < 10))
	      speed+=dec;
	  }
	}
	buf_ptr = buff;
	if (partial_V != NULL) { // Copy partial to start of buff[]
	  while (partial_V < buf_end) {
	    // *(buf_ptr++) = *(partial_V++);
	    *buf_ptr = *partial_V;
	    buf_ptr++; partial_V++;
	  }
	}
	if (verbose > 1) {
	  printf("Radar speed %d\n", speed);
	}
	return speed;
}

int accept_client(int client_fds[], int max_clients, int *num_clients, int listen_fd)
{
	int new_conn = accept(listen_fd, NULL, NULL);

	if (new_conn >= 0) {
	  for (int i=0; i < max_clients; i++) {
	    if (client_fds[i] < 0) {
	      client_fds[i] = new_conn;
	      (*num_clients)++;
	      int flags = fcntl(new_conn, F_GETFL, 0);
	      if (flags != -1) {
	        (void) fcntl(new_conn, F_SETFL, flags | O_NONBLOCK);
	      }
	      if (verbose > 1) printf("New client %d of %d\n", i, *num_clients);
	      break;
	    }
	  }
	}
	return new_conn;
}

void handle_keepalives(int client_fd, int client_fds[], int max_clients, int *num_clients)
{
	char	krud[512];

	int len = read(client_fd, krud, sizeof(krud));

	if (len == 0) {
	  // End of file - POLL said there was data, but read had 0
	  for(int i=0; i < max_clients; i++) {
	    if (client_fds[i] == client_fd) {
	      client_fds[i] = -1;
	      (*num_clients)--;
	      if (verbose > 1) printf("Disconnected client %d, %d remain\n", i, *num_clients);
	      break;
	    }
	  }
	  close(client_fd);
	}
}

// void update_client(int fd, int max_speed, int cnr_speed, int curr_speed)
void update_client(int fd, struct time_struct_s *ts)
{
	char	Speed_Update[512];

	int len=snprintf(Speed_Update, sizeof(Speed_Update), "S%d.%d M%d.%d C%d.%d N%d T%d\n",
		ts->curr_speed / 10, ts->curr_speed % 10,
		ts->max_speed / 10, ts->max_speed % 10,
		ts->cnr_speed / 10, ts->cnr_speed % 10,
		ts->cnt, ts->time
	);

	write(fd, Speed_Update, len);
}

// void update_clients(int client_fds[], int max_clients, int max_speed, int cnr_speed, int curr_speed)
void update_clients(int client_fds[], int max_clients, struct time_struct_s *ts)
{
	for(int i=0; i < max_clients; i++) {
	  if (client_fds[i] >= 0) {
	    update_client(client_fds[i], ts);
          }
        }
}


#define	MAX_CLIENTS	10
enum poll_fds { SERIAL=0, LISTEN, CONN0, MAX_POLL = MAX_CLIENTS+2 };
void main_loop(int radar_fd, int listen_fd)
{
	int		num_clients = 0;
	int		clients[MAX_CLIENTS];
	struct pollfd	fd_watch[MAX_POLL];

	struct time_struct_s	ts_data;
	struct time_struct_s	*ts = &ts_data;

	memset(ts, 0, sizeof(*ts));
	time_t		max_speed_time = 0;
	//int		max_speed = 0;
	//int		cnr_speed = 0;
	//int		curr_speed = 0;

	for(int i=0; i < MAX_CLIENTS; i++) {
	  clients[i] = -1;
	}

	fd_watch[SERIAL].fd = radar_fd;
	fd_watch[SERIAL].events = POLLIN;
	fd_watch[LISTEN].fd = listen_fd;

	time_t	last_update = 0;
	time_t	time_base = 0;

	int prev_speed=0;
	while (1) {
	  int got_new_speed;
	  int poll_slot;

	  got_new_speed = 0;
	  if (num_clients < MAX_CLIENTS) {
	    fd_watch[LISTEN].events = POLLIN;
          }
	  else {  // Have max clients, don't respond to a new connection
	    fd_watch[LISTEN].events = 0;
          }
	  poll_slot = CONN0;
	  for(int i=0; i < MAX_CLIENTS; i++) {
            if (clients[i] >= 0) {
	      fd_watch[poll_slot].fd = clients[i];
	      fd_watch[poll_slot].events = POLLIN | POLLHUP;
	      poll_slot++;
            }
          }

          int num_evt = poll(fd_watch, poll_slot, 1000);
	  time_t	now = time_h(NULL);
          if (num_evt > 0) {
            // Have IO events
	    if (fd_watch[SERIAL].revents & POLLIN) {
	      int new_speed = get_speed(radar_fd);
	      num_evt--;
	      if (new_speed > 0) {
	        got_new_speed = 1;
		ts->curr_speed = new_speed;
	        if ((now - max_speed_time) > run_gap) {
		  // New pass, restart timers
		  ts->max_speed = 0;
		  ts->cnt = 0;
		  time_base=now;
		  ts->time = 0;
		  prev_speed=0;
		}
		else {
		  ts->cnt++;
		  ts->time = now - time_base;
		}
	        if (new_speed > ts->max_speed) {
		  ts->max_speed = new_speed;
		  ts->cnr_speed = new_speed;
	          max_speed_time = now;
		}
		else if (new_speed < ts->cnr_speed) {
		  ts->cnr_speed = new_speed;
		}
		if (verbose) {
		  printf("Speeds %ld Curr %3d.%d  Max %3d.%d  Cnr %3d.%d\n",
			now,
			ts->curr_speed / 10, ts->curr_speed % 10,
			ts->max_speed / 10, ts->max_speed % 10,
			ts->cnr_speed / 10, ts->cnr_speed % 10);
		}
		if ((new_speed < ts->max_speed) && (prev_speed == ts->max_speed)) {
		  printf("Time: %ld MaxSpeed: %3d.%d\n", time(NULL),
			ts->max_speed / 10, ts->max_speed % 10);
		}
		prev_speed = new_speed;
	      }
	    }
	    if (fd_watch[LISTEN].revents & POLLIN) {
	      num_evt--;
	      int new_client = accept_client(clients, MAX_CLIENTS, &num_clients, listen_fd);
	      if (new_client >= 0) {
	            update_client(new_client, ts);
              }
            }
	    for (int i=CONN0; i < poll_slot; i++) {
	      if (fd_watch[i].revents & POLLIN) {
	        handle_keepalives(fd_watch[i].fd, clients, MAX_CLIENTS, &num_clients);
	      }
            }
          }
	  if (got_new_speed || ((last_update + 1000) < now)) {
	    if (verbose>2)
	      printf("Do update  new speed %d  last_update %ld  now  %ld\n", got_new_speed, last_update, now);
            update_clients(clients, MAX_CLIENTS, ts);
	    last_update = now;
	  }
	}
}

int create_listen(int port)
{
	struct sockaddr_in	addr;

	addr.sin_family = AF_INET;
	addr.sin_port = htons(port);
	//addr.sin_addr.s_addr = htonl(INADDR_ANY);
	addr.sin_addr.s_addr = htonl(INADDR_LOOPBACK);

	int	s;
	s = socket(AF_INET, SOCK_STREAM, 0);
	if (s < 0) do_perror("Creating listen socket");

	int	val = 1;
        if (setsockopt(s, SOL_SOCKET, SO_REUSEADDR, &val, sizeof(val)) < 0)
	    do_perror("setsockopt SO_REUSEADDR");
	if (bind(s, (struct sockaddr *)&addr, sizeof(addr)) < 0)
	    do_perror("do bind()");
#define LISTEN_BACKLOG 10
	if (listen(s, LISTEN_BACKLOG) < 0)
	    do_perror("set listen()");

	return s;
}

int main(int argc, char **argv)
{
	int	radar_fd = -1;
	int	listen_fd = -1;

	time_h_setbase(NULL);

	readConf("/etc/radar/radar.conf");

	radar_fd = set_radar_serial(serial_name);

	/* Configure the Radar */
	write( radar_fd, conf_q, sizeof(conf_q)-1);
	usleep(200000);
	write( radar_fd, (void *)&cmd1, sizeof(cmd1));
	usleep(200000);
	write( radar_fd, (void *)&cmd2, sizeof(cmd2));
	usleep(200000);
	write( radar_fd, conf_q, sizeof(conf_q)-1);
	usleep(200000);

	listen_fd = create_listen(port);
	main_loop(radar_fd, listen_fd);

}
