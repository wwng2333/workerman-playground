package main

import (
	"encoding/json"
	"fmt"
	"io"
	"net"
	"net/http"
	"os"
	"time"
)

type Result struct {
	Size    int64   `json:"size"`
	Elapsed float64 `json:"elapsed"`
	Speed   float64 `json:"speed"`
}

func main() {
	if len(os.Args) < 3 {
		fmt.Println(`{"error":"Usage: go run main.go <url> <ip_version>"}`)
		return
	}

	url := os.Args[1]
	ipVersion := os.Args[2]

	var dialFunc func(network, addr string) (net.Conn, error)

	switch ipVersion {
	case "ipv4":
		dialFunc = func(network, addr string) (net.Conn, error) {
			return net.Dial("tcp4", addr)
		}
	case "ipv6":
		dialFunc = func(network, addr string) (net.Conn, error) {
			return net.Dial("tcp6", addr)
		}
	default:
		fmt.Printf(`{"error":"Unknown IP version: %s. Expected 'ipv4' or 'ipv6'."}`, ipVersion)
		return
	}

	client := http.Client{
		Transport: &http.Transport{
			Dial: dialFunc,
		},
		Timeout: time.Duration(60 * time.Second),
	}

	start := time.Now()

	resp, err := client.Get(url)
	if err != nil {
		fmt.Printf(`{"error":"%s"}`, err)
		return
	}
	defer resp.Body.Close()

	out, err := os.Create("/dev/null")
	if err != nil {
		fmt.Printf(`{"error":"%s"}`, err)
		return
	}
	defer out.Close()

	n, err := io.Copy(out, resp.Body)
	if err != nil {
		fmt.Printf(`{"error":"%s"}`, err)
		return
	}

	elapsed := time.Since(start)
	speed := float64(n) / elapsed.Seconds() / (1024 * 1024)

	result := Result{
		Size:    n,
		Elapsed: elapsed.Seconds(),
		Speed:   speed,
	}

	jsonResult, err := json.Marshal(result)
	if err != nil {
		fmt.Printf(`{"error":"%s"}`, err)
		return
	}

	fmt.Println(string(jsonResult))
}
